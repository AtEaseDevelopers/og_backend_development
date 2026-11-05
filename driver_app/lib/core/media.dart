import 'dart:io';
import 'dart:typed_data';

import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

/// Photos and signatures are copied into the app's own storage, so they survive until the record is synced
/// (the camera's temp files may be cleared by the OS at any time).
class MediaStore {
  static final _picker = ImagePicker();
  static const _uuid = Uuid();

  static Future<Directory> _dir() async {
    final dir = Directory(p.join((await getApplicationDocumentsDirectory()).path, 'captures'));
    if (!await dir.exists()) await dir.create(recursive: true);
    return dir;
  }

  static Future<String?> takePhoto({bool fromGallery = false}) async {
    final picked = await _picker.pickImage(
      source: fromGallery ? ImageSource.gallery : ImageSource.camera,
      imageQuality: 75,
      maxWidth: 1800,
    );
    if (picked == null) return null;

    final ext = p.extension(picked.path).isEmpty ? '.jpg' : p.extension(picked.path);
    final target = p.join((await _dir()).path, '${_uuid.v4()}$ext');
    await File(picked.path).copy(target);
    return target;
  }

  static Future<String> saveBytes(Uint8List bytes, {String ext = '.png'}) async {
    final target = p.join((await _dir()).path, '${_uuid.v4()}$ext');
    await File(target).writeAsBytes(bytes, flush: true);
    return target;
  }

  static Future<void> delete(String path) async {
    final f = File(path);
    if (await f.exists()) await f.delete();
  }
}
