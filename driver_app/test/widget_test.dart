import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:image/image.dart' as img;
import 'package:og_driver/core/blur.dart';
import 'package:og_driver/core/config.dart';
import 'package:og_driver/models/models.dart';

Uint8List _checkerboard({int size = 200, int cell = 10}) {
  final image = img.Image(width: size, height: size);
  for (var y = 0; y < size; y++) {
    for (var x = 0; x < size; x++) {
      final on = ((x ~/ cell) + (y ~/ cell)).isEven;
      image.setPixelRgb(x, y, on ? 255 : 0, on ? 255 : 0, on ? 255 : 0);
    }
  }
  return Uint8List.fromList(img.encodePng(image));
}

void main() {
  test('sharp image passes the blur check, blurred image is flagged', () {
    final sharp = _checkerboard();
    final blurred = Uint8List.fromList(img.encodePng(img.gaussianBlur(img.decodePng(sharp)!, radius: 8)));

    expect(BlurDetector.laplacianVariance(sharp), greaterThan(AppConfig.blurThreshold));
    expect(BlurDetector.laplacianVariance(blurred), lessThan(AppConfig.blurThreshold));
  });

  test('job sheet groups tasks by route in planned sequence', () {
    final sheet = JobSheet({
      'id': 1,
      'number': 'KL-JS-1',
      'status': 'in_transit',
      'tasks': [
        {'delivery_order_id': 3, 'do_number': 'DO-3', 'sequence': 3, 'route_group': 'Johor', 'scan_codes': []},
        {'delivery_order_id': 2, 'do_number': 'DO-2', 'sequence': 2, 'route_group': 'Selangor', 'scan_codes': []},
        {'delivery_order_id': 1, 'do_number': 'DO-1', 'sequence': 1, 'route_group': 'Selangor', 'scan_codes': []},
      ],
    });

    expect(sheet.byRoute.keys, ['Johor', 'Selangor']);
    expect(sheet.byRoute['Selangor']!.map((t) => t.doNumber), ['DO-1', 'DO-2']);
  });
}
