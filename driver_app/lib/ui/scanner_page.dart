import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

/// Scans one QR / barcode and returns its value (or a manually typed code for a damaged label).
class ScannerPage extends StatefulWidget {
  const ScannerPage({super.key, required this.title});

  final String title;

  @override
  State<ScannerPage> createState() => _ScannerPageState();
}

class _ScannerPageState extends State<ScannerPage> {
  final _controller = MobileScannerController(detectionSpeed: DetectionSpeed.noDuplicates);
  bool _done = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _finish(String code) {
    if (_done || code.trim().isEmpty) return;
    _done = true;
    Navigator.pop(context, code.trim());
  }

  Future<void> _manual() async {
    final code = await promptManualCode(context);
    if (code != null) _finish(code);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            onPressed: () => _controller.toggleTorch(),
            icon: const Icon(Icons.flashlight_on_outlined),
            tooltip: 'Torch',
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: (capture) {
              final value = capture.barcodes.map((b) => b.rawValue).whereType<String>().firstOrNull;
              if (value != null) _finish(value);
            },
          ),
          const ScanFrame(),
          Positioned(
            left: 24,
            right: 24,
            bottom: 32,
            child: FilledButton.tonalIcon(
              onPressed: _manual,
              icon: const Icon(Icons.keyboard),
              label: const Text('Enter code manually'),
            ),
          ),
        ],
      ),
    );
  }
}

Future<String?> promptManualCode(BuildContext context) {
  final ctrl = TextEditingController();
  return showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Enter CSN / DO number'),
      content: TextField(
        controller: ctrl,
        autofocus: true,
        textCapitalization: TextCapitalization.characters,
        decoration: const InputDecoration(hintText: 'e.g. KL-CSN-202610-0001'),
        onSubmitted: (v) => Navigator.pop(ctx, v.trim().isEmpty ? null : v.trim()),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, ctrl.text.trim().isEmpty ? null : ctrl.text.trim()),
          child: const Text('OK'),
        ),
      ],
    ),
  );
}

class ScanFrame extends StatelessWidget {
  const ScanFrame({super.key});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Center(
        child: Container(
          width: 240,
          height: 240,
          decoration: BoxDecoration(
            border: Border.all(color: Colors.white, width: 3),
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}
