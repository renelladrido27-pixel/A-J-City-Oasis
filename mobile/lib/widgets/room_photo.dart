import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../theme.dart';

/// One room photo from the server — the same files the website shows. The
/// site's sample photos are SVG drawings and real uploads are JPG/PNG/WebP,
/// so both are handled. Falls back to a neutral tile if there is no photo or
/// it fails to load.
class RoomPhoto extends StatelessWidget {
  final String? url;
  final BoxFit fit;
  const RoomPhoto({super.key, required this.url, this.fit = BoxFit.cover});

  @override
  Widget build(BuildContext context) {
    final src = url;
    if (src == null || src.isEmpty) return const RoomPhotoPlaceholder();

    if (Uri.tryParse(src)?.path.toLowerCase().endsWith('.svg') ?? false) {
      return SvgPicture.network(
        src,
        fit: fit,
        placeholderBuilder: (_) => const RoomPhotoPlaceholder(loading: true),
        errorBuilder: (_, _, _) => const RoomPhotoPlaceholder(),
      );
    }

    return Image.network(
      src,
      fit: fit,
      loadingBuilder: (context, child, progress) =>
          progress == null ? child : const RoomPhotoPlaceholder(loading: true),
      errorBuilder: (_, _, _) => const RoomPhotoPlaceholder(),
    );
  }
}

class RoomPhotoPlaceholder extends StatelessWidget {
  final bool loading;
  const RoomPhotoPlaceholder({super.key, this.loading = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFFE6ECE8),
      alignment: Alignment.center,
      child: loading
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: OasisColors.green,
              ),
            )
          : const Icon(
              Icons.bed_outlined,
              size: 34,
              color: OasisColors.placeholderGrey,
            ),
    );
  }
}

/// Swipeable photos with page dots — the app's version of the website's
/// room gallery.
class RoomGallery extends StatefulWidget {
  final List<String> images;
  final double height;

  /// Corner rounding; 0 when the gallery sits flush inside a card.
  final double radius;
  const RoomGallery({
    super.key,
    required this.images,
    this.height = 190,
    this.radius = 10,
  });

  @override
  State<RoomGallery> createState() => _RoomGalleryState();
}

class _RoomGalleryState extends State<RoomGallery> {
  int _page = 0;

  @override
  Widget build(BuildContext context) {
    final images = widget.images;
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: SizedBox(
        height: widget.height,
        child: images.isEmpty
            ? const RoomPhotoPlaceholder()
            : Stack(
                fit: StackFit.expand,
                children: [
                  PageView.builder(
                    itemCount: images.length,
                    onPageChanged: (i) => setState(() => _page = i),
                    itemBuilder: (_, i) => RoomPhoto(url: images[i]),
                  ),
                  if (images.length > 1)
                    Positioned(
                      bottom: 8,
                      left: 0,
                      right: 0,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          for (var i = 0; i < images.length; i++)
                            Container(
                              width: 7,
                              height: 7,
                              margin: const EdgeInsets.symmetric(horizontal: 3),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: i == _page
                                    ? Colors.white
                                    : Colors.white.withValues(alpha: 0.5),
                              ),
                            ),
                        ],
                      ),
                    ),
                  if (images.length > 1)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.black.withValues(alpha: 0.55),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          '${_page + 1} / ${images.length}',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}
