import 'package:geolocator/geolocator.dart';

class GeoPoint {
  const GeoPoint(this.latitude, this.longitude, {this.accuracy});

  final double latitude;
  final double longitude;
  final double? accuracy;

  String get label => '${latitude.toStringAsFixed(6)}, ${longitude.toStringAsFixed(6)}';
}

/// GPS is captured automatically with every record; works offline (satellite fix, no data needed).
class LocationService {
  static Future<GeoPoint?> current() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return _lastKnown();

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) return null;

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 12)),
      );
      return GeoPoint(pos.latitude, pos.longitude, accuracy: pos.accuracy);
    } catch (_) {
      return _lastKnown();
    }
  }

  static Future<GeoPoint?> _lastKnown() async {
    try {
      final pos = await Geolocator.getLastKnownPosition();
      return pos == null ? null : GeoPoint(pos.latitude, pos.longitude, accuracy: pos.accuracy);
    } catch (_) {
      return null;
    }
  }
}
