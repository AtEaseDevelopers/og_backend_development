import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:image/image.dart' as img;

import 'config.dart';

class BlurResult {
  const BlurResult(this.score);

  final double score;

  bool get isBlurred => score < AppConfig.blurThreshold;
}

/// Sharpness check by the variance of the Laplacian: a sharp photo has strong edges (high variance), a blurred
/// one has almost none. Runs in a background isolate so the UI stays responsive.
class BlurDetector {
  static Future<BlurResult> check(String path) async {
    final bytes = await File(path).readAsBytes();
    return BlurResult(await compute(laplacianVariance, bytes));
  }

  static double laplacianVariance(Uint8List bytes) {
    final decoded = img.decodeImage(bytes);
    if (decoded == null) return double.infinity;

    final small = img.grayscale(decoded.width > 480 ? img.copyResize(decoded, width: 480) : decoded);
    final w = small.width;
    final h = small.height;
    if (w < 3 || h < 3) return double.infinity;

    final gray = List<double>.filled(w * h, 0);
    for (var y = 0; y < h; y++) {
      for (var x = 0; x < w; x++) {
        gray[y * w + x] = small.getPixel(x, y).r.toDouble();
      }
    }

    var sum = 0.0;
    var sumSq = 0.0;
    var n = 0;
    for (var y = 1; y < h - 1; y++) {
      for (var x = 1; x < w - 1; x++) {
        final i = y * w + x;
        final lap = gray[i - w] + gray[i + w] + gray[i - 1] + gray[i + 1] - 4 * gray[i];
        sum += lap;
        sumSq += lap * lap;
        n++;
      }
    }
    final mean = sum / n;
    return sumSq / n - mean * mean;
  }
}
