import 'dart:io';

import 'package:flutter/material.dart';

import '../../core/blur.dart';
import '../../core/media.dart';
import 'common.dart';

/// Photo capture kept on the phone until sync. With [blurCheck], each photo is checked for blur and the driver
/// is warned before keeping a blurred one (item 32).
class PhotoField extends StatefulWidget {
  const PhotoField({
    super.key,
    required this.photos,
    required this.onChanged,
    this.blurCheck = false,
    this.allowGallery = false,
    this.max = 6,
    this.addLabel = 'Take photo',
  });

  final List<String> photos;
  final ValueChanged<List<String>> onChanged;
  final bool blurCheck;
  final bool allowGallery;
  final int max;
  final String addLabel;

  @override
  State<PhotoField> createState() => _PhotoFieldState();
}

class _PhotoFieldState extends State<PhotoField> {
  bool _busy = false;
  final Set<String> _blurred = {};

  Future<void> _add({bool gallery = false}) async {
    setState(() => _busy = true);
    try {
      final path = await MediaStore.takePhoto(fromGallery: gallery);
      if (path == null) return;

      if (widget.blurCheck) {
        final result = await BlurDetector.check(path);
        if (result.isBlurred && mounted) {
          final keep = await showDialog<bool>(
            context: context,
            builder: (ctx) => AlertDialog(
              icon: Icon(Icons.blur_on, color: Colors.orange.shade800, size: 36),
              title: const Text('Image appears blurred'),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(File(path), height: 160, fit: BoxFit.cover),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'The text on this POD may not be readable (sharpness ${result.score.toStringAsFixed(0)}). Retake the photo with the camera held steady.',
                  ),
                ],
              ),
              actions: [
                TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Use anyway')),
                FilledButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Retake')),
              ],
            ),
          );
          if (keep != true) {
            await MediaStore.delete(path);
            if (keep == false && mounted) return _add(gallery: gallery);
            return;
          }
          _blurred.add(path);
        }
      }

      widget.onChanged([...widget.photos, path]);
    } catch (e) {
      if (mounted) showMessage(context, 'Camera error: ${errorText(e)}', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (widget.photos.isNotEmpty)
          SizedBox(
            height: 96,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: widget.photos.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final path = widget.photos[i];
                return Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.file(File(path), width: 96, height: 96, fit: BoxFit.cover),
                    ),
                    if (_blurred.contains(path))
                      Positioned(
                        left: 4,
                        bottom: 4,
                        child: Pill('Blurred', color: Colors.orange.shade900, icon: Icons.blur_on),
                      ),
                    Positioned(
                      right: 0,
                      top: 0,
                      child: InkWell(
                        onTap: () {
                          _blurred.remove(path);
                          MediaStore.delete(path);
                          widget.onChanged([...widget.photos]..removeAt(i));
                        },
                        child: Container(
                          decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                          padding: const EdgeInsets.all(3),
                          child: const Icon(Icons.close, color: Colors.white, size: 16),
                        ),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        if (widget.photos.isNotEmpty) const SizedBox(height: 8),
        if (widget.photos.length < widget.max)
          Wrap(
            spacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: _busy ? null : () => _add(),
                icon: _busy
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.photo_camera_outlined),
                label: Text(widget.addLabel),
              ),
              if (widget.allowGallery)
                TextButton.icon(
                  onPressed: _busy ? null : () => _add(gallery: true),
                  icon: const Icon(Icons.photo_library_outlined),
                  label: const Text('From gallery'),
                ),
            ],
          ),
        if (widget.photos.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text('No photo yet', style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor)),
          ),
      ],
    );
  }
}
