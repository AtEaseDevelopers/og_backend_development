import 'package:intl/intl.dart';

typedef Json = Map<String, dynamic>;

String? _s(dynamic v) => v?.toString();
double? _d(dynamic v) => v == null ? null : double.tryParse(v.toString());
DateTime? _t(dynamic v) => v == null ? null : DateTime.tryParse(v.toString())?.toLocal();

final _dateTime = DateFormat('d MMM yyyy, HH:mm');
final _money = NumberFormat.currency(locale: 'en_MY', symbol: 'RM ', decimalDigits: 2);

String formatDateTime(DateTime? t) => t == null ? '—' : _dateTime.format(t);
String formatMoney(num? v) => v == null ? '—' : _money.format(v);

class Option {
  const Option(this.value, this.label);

  factory Option.fromJson(Json j) => Option(j['value'].toString(), j['label'].toString());

  final String value;
  final String label;
}

class Meta {
  Meta({required this.failureReasons, required this.breakBulkReasons, required this.codMethods, required this.driver});

  factory Meta.fromJson(Json j) => Meta(
    failureReasons: (j['failure_reasons'] as List? ?? []).map((e) => Option.fromJson(Json.from(e))).toList(),
    breakBulkReasons: (j['break_bulk_reasons'] as List? ?? []).map((e) => Option.fromJson(Json.from(e))).toList(),
    codMethods: (j['cod_payment_methods'] as List? ?? []).map((e) => Option.fromJson(Json.from(e))).toList(),
    driver: Json.from(j['driver'] as Map? ?? {}),
  );

  final List<Option> failureReasons;
  final List<Option> breakBulkReasons;
  final List<Option> codMethods;
  final Json driver;
}

class TripSummary {
  TripSummary(this.raw);

  final Json raw;

  int get id => raw['id'] as int;
  String get number => _s(raw['number']) ?? '';
  String get tripLabel => _s(raw['trip_label']) ?? 'Trip';
  String get status => _s(raw['status']) ?? 'draft';
  int get taskCount => (raw['task_count'] as num?)?.toInt() ?? 0;
}

class JobSheet {
  JobSheet(this.raw) : tasks = (raw['tasks'] as List? ?? []).map((t) => DriverTask(Json.from(t as Map))).toList();

  final Json raw;
  final List<DriverTask> tasks;

  int get id => raw['id'] as int;
  String get number => _s(raw['number']) ?? '';
  String get tripLabel => _s(raw['trip_label']) ?? '';
  String get status => _s(raw['status']) ?? 'draft';
  String get statusLabel => _s(raw['status_label']) ?? status;
  bool get isDraft => status == 'draft';
  bool get isInTransit => status == 'in_transit';
  bool get isCompleted => status == 'completed';
  String? get operatingDate => _s(raw['operating_date']);
  DateTime? get checkedInAt => _t(raw['checked_in_at']);
  Json get lorry => Json.from(raw['lorry'] as Map? ?? {});
  int? get lorryId => lorry['id'] as int?;
  String get lorryNo => _s(lorry['registration_no']) ?? '—';
  String? get operatingBranch {
    final b = raw['operating_branch'];
    return b is Map ? '${b['code']} · ${b['name']}' : null;
  }

  bool get isSharedDispatch => raw['is_shared_dispatch'] == true;
  Json? get scanValidation => raw['scan_validation'] is Map ? Json.from(raw['scan_validation'] as Map) : null;

  /// Tasks grouped by route, each group in planned sequence.
  Map<String, List<DriverTask>> get byRoute {
    final groups = <String, List<DriverTask>>{};
    for (final t in tasks) {
      groups.putIfAbsent(t.routeGroup, () => []).add(t);
    }
    for (final g in groups.values) {
      g.sort((a, b) => (a.sequence ?? 999).compareTo(b.sequence ?? 999));
    }
    return groups;
  }
}

class DriverTask {
  DriverTask(this.raw);

  final Json raw;

  int get id => raw['delivery_order_id'] as int;
  String get doNumber => _s(raw['do_number']) ?? '';
  String? get csnNumber => _s(raw['csn_number']);
  String? get csnQrToken => _s(raw['csn_qr_token']);
  String? get customerDoNumber => _s(raw['customer_do_number']);
  String get status => _s(raw['status']) ?? 'assigned';
  String get statusLabel => _s(raw['status_label']) ?? status;
  int? get sequence => (raw['sequence'] as num?)?.toInt();
  String get routeGroup => _s(raw['route_group']) ?? 'Unrouted';
  String get kind => _s(raw['task_kind']) ?? 'delivery';
  String get kindLabel => _s(raw['task_label']) ?? 'Delivery';
  bool get claimPending => raw['claim_pending'] == true;

  String? get sourceBranch {
    final b = raw['source_branch'];
    return b is Map ? '${b['code']} · ${b['name']}' : null;
  }

  String? get customerName => _s(raw['customer_name']);
  String? get consigneeName => _s(raw['consignee_name']);
  String? get consigneePic => _s(raw['consignee_pic']);
  String? get consigneePhone => _s(raw['consignee_phone']);
  String? get consignorName => _s(raw['consignor_name']);
  String? get consignorAddress => _s(raw['consignor_address']);
  String get address {
    final street = _s(raw['address']) ?? '';
    final extras = [
      raw['postcode'],
      raw['city'],
      raw['state'],
    ].map(_s).whereType<String>().where((v) => v.isNotEmpty && !street.toLowerCase().contains(v.toLowerCase()));
    return [if (street.isNotEmpty) street, ...extras.toSet()].join(', ');
  }

  String? get remarks => _s(raw['remarks']);
  bool get isCod => raw['is_cod'] == true;
  double? get codAmount => _d(raw['cod_amount']);
  List<Json> get items => (raw['items'] as List? ?? []).map((e) => Json.from(e as Map)).toList();
  String get itemSummary => _s(raw['item_summary']) ?? '';
  double get totalQuantity => _d(raw['total_quantity']) ?? 0;
  List<String> get scanCodes => (raw['scan_codes'] as List? ?? []).map((e) => e.toString()).toList();

  Json? get transferCode => raw['transfer_code'] is Map ? Json.from(raw['transfer_code'] as Map) : null;
  String? get transferCodeLabel => transferCode?['code']?.toString();
  String? get destinationBranch {
    final b = transferCode?['destination_branch'];
    return b is Map ? '${b['code']} · ${b['name']}' : null;
  }

  Json? get subsheet => raw['subsheet'] is Map ? Json.from(raw['subsheet'] as Map) : null;
  Json? get breakBulk => raw['break_bulk'] is Map ? Json.from(raw['break_bulk'] as Map) : null;
  Json? get failed => raw['failed'] is Map ? Json.from(raw['failed'] as Map) : null;
  String? get parentDoNumber => _s(raw['parent_do_number']);
  bool get isDuplicate => raw['is_duplicate'] == true;
  DateTime? get pickupRecordedAt => _t(raw['pickup_recorded_at']);
  DateTime? get handoverRecordedAt => _t(raw['handover_recorded_at']);

  bool get isAdditionalPickup => kind == 'additional_pickup';
  bool get isOpen => const ['assigned', 'in_transit', 'reassigned'].contains(status);
  bool get hasActiveBreakBulk => breakBulk != null && breakBulk!['status'] == 'active';

  String get displayRef => [csnNumber, doNumber].whereType<String>().where((s) => s.isNotEmpty).join(' / ');
}
