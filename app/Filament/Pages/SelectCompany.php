<?php

namespace App\Filament\Pages;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Company;
use App\Support\SelectedBranch;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Panel;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @property Form $form
 */
class SelectCompany extends SimplePage implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.select-company';

    protected static bool $isDiscovered = false;

    protected static ?string $slug = 'select-company';

    public ?array $data = [];

    public bool $showRegisterForm = false;

    public function mount(): void
    {
        abort_unless(Filament::auth()->check(), 403);

        $branch = SelectedBranch::get();
        if (! $branch instanceof Branch) {
            $this->redirect(route('filament.admin.select-branch'));

            return;
        }

        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        abort_unless($user->canAccessBranch($branch), 403);

        $this->form->fill([
            'code' => '',
            'name' => '',
            'brn' => '',
            'tin' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
        ]);
    }

    public function getTitle(): string | Htmlable
    {
        return 'Choose company';
    }

    public function getHeading(): string | Htmlable
    {
        $branch = SelectedBranch::get();

        return $branch ? "Companies in {$branch->code}" : 'Choose company';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Select a registered company to open its system, or register a new company with BRN.';
    }

    public function getMaxWidth(): MaxWidth | string | null
    {
        return MaxWidth::FourExtraLarge;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Company code')
                            ->helperText('Short unique code used in the admin URL (e.g. OGKL2).')
                            ->required()
                            ->maxLength(40)
                            ->alphaDash()
                            ->rule(Rule::unique('companies', 'code')),
                        Forms\Components\TextInput::make('name')
                            ->label('Company name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('brn')
                            ->label('BRN / company no.')
                            ->required()
                            ->maxLength(100)
                            ->rule(function () {
                                $branchId = SelectedBranch::id();

                                return Rule::unique('companies', 'brn')->where(fn ($q) => $q->where('branch_id', $branchId));
                            }),
                        Forms\Components\TextInput::make('tin')->label('TIN')->maxLength(100),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\Textarea::make('address')->rows(3)->columnSpanFull(),
                    ])
                    ->extraAttributes([
                        'style' => 'row-gap: 1.75rem; column-gap: 1.5rem;',
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $branch = SelectedBranch::get();

        if (! $branch) {
            return collect();
        }

        return $user->accessibleCompaniesForBranch($branch);
    }

    public function enterCompany(int $companyId): void
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');
        $company = Company::query()->findOrFail($companyId);

        abort_unless($user->canAccessTenant($company), 403);
        abort_unless($company->branch_id === SelectedBranch::id(), 403);

        $this->redirect($panel->getUrl($company));
    }

    public function toggleRegisterForm(): void
    {
        $this->showRegisterForm = ! $this->showRegisterForm;
    }

    public function registerCompany(): void
    {
        /** @var \App\Models\User $user */
        $user = Filament::auth()->user();
        $branch = SelectedBranch::get();
        abort_unless($branch instanceof Branch && $user->canAccessBranch($branch), 403);

        $data = $this->form->getState();
        $code = Str::upper(trim((string) $data['code']));

        $company = DB::transaction(function () use ($user, $branch, $data, $code) {
            $company = Company::query()->create([
                'branch_id' => $branch->id,
                'code' => $code,
                'name' => $data['name'],
                'brn' => $data['brn'],
                'tin' => $data['tin'] ?: null,
                'address' => $data['address'] ?: null,
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'is_active' => true,
                'registered_by' => $user->id,
            ]);

            if (! $user->branches()->where('branches.id', $branch->id)->exists()) {
                $user->branches()->attach($branch->id, ['is_default' => false]);
            }

            $user->companies()->syncWithoutDetaching([
                $company->id => ['is_default' => ! $user->companies()->exists()],
            ]);

            return $company;
        });

        Notification::make()
            ->title('Company registered')
            ->body("{$company->name} is ready.")
            ->success()
            ->send();

        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');
        $this->redirect($panel->getUrl($company));
    }

    public function backToBranches(): void
    {
        SelectedBranch::clear();
        $this->redirect(route('filament.admin.select-branch'));
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        return route('filament.admin.select-company', $parameters, $isAbsolute);
    }

    public static function registerRoutes(Panel $panel): void
    {
        // Registered via authenticatedRoutes in AdminPanelProvider.
    }
}
