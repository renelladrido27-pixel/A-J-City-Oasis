import 'package:flutter/foundation.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../theme.dart';

/// The same Google map the website shows in "Find us in Koronadal City",
/// with a button to open it in the Maps app for directions.
class LocationMap extends StatefulWidget {
  /// What the map is centred on — kept identical to the website's map.
  static const query = 'Koronadal City, South Cotabato';

  const LocationMap({super.key});

  static Uri get embedUri =>
      Uri.https('www.google.com', '/maps', {'q': query, 'output': 'embed'});

  static Uri get directionsUri => Uri.https('www.google.com', '/maps/search/', {
    'api': '1',
    'query': query,
  });

  @override
  State<LocationMap> createState() => _LocationMapState();
}

class _LocationMapState extends State<LocationMap> {
  WebViewController? _controller;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    // No web view in widget tests (or an unsupported platform): the card then
    // shows just the "Open in Google Maps" button.
    if (WebViewPlatform.instance != null) {
      _controller = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setBackgroundColor(OasisColors.sand)
        ..setNavigationDelegate(
          NavigationDelegate(
            onPageFinished: (_) {
              if (mounted) setState(() => _loading = false);
            },
            // Taps on "View larger map" etc. go to the Maps app, not inside the card.
            onNavigationRequest: (request) {
              if (request.url.startsWith(LocationMap.embedUri.toString())) {
                return NavigationDecision.navigate;
              }
              launchUrl(
                Uri.parse(request.url),
                mode: LaunchMode.externalApplication,
              );
              return NavigationDecision.prevent;
            },
          ),
        )
        ..loadRequest(LocationMap.embedUri);
    }
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (controller != null)
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: AspectRatio(
              aspectRatio: 16 / 10,
              child: Stack(
                children: [
                  Positioned.fill(
                    child: WebViewWidget(
                      controller: controller,
                      // Drag the map itself instead of scrolling the page.
                      gestureRecognizers: {
                        Factory<OneSequenceGestureRecognizer>(
                          EagerGestureRecognizer.new,
                        ),
                      },
                    ),
                  ),
                  if (_loading)
                    const Positioned.fill(
                      child: ColoredBox(
                        color: OasisColors.sand,
                        child: Center(child: CircularProgressIndicator()),
                      ),
                    ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => launchUrl(
            LocationMap.directionsUri,
            mode: LaunchMode.externalApplication,
          ),
          icon: const Icon(Icons.map_outlined, size: 18),
          label: const Text('Open in Google Maps'),
          style: OutlinedButton.styleFrom(
            foregroundColor: OasisColors.green,
            minimumSize: const Size.fromHeight(40),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),
      ],
    );
  }
}
