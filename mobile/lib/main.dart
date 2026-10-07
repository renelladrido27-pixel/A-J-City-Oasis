import 'package:flutter/material.dart';

import 'state/app_state.dart';
import 'screens/root_shell.dart';
import 'theme.dart';

void main() {
  runApp(AJOasisApp(appState: AppState()));
}

class AJOasisApp extends StatefulWidget {
  final AppState appState;
  const AJOasisApp({super.key, required this.appState});

  @override
  State<AJOasisApp> createState() => _AJOasisAppState();
}

class _AJOasisAppState extends State<AJOasisApp> {
  @override
  void initState() {
    super.initState();
    // AJOasisApp provides AppStateScope but doesn't consume it, so it won't
    // rebuild on notifyListeners() on its own — listen explicitly so the
    // splash screen actually swaps to RootShell once bootstrap() finishes.
    widget.appState.addListener(_onAppStateChanged);
    widget.appState.bootstrap();
  }

  @override
  void dispose() {
    widget.appState.removeListener(_onAppStateChanged);
    super.dispose();
  }

  void _onAppStateChanged() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return AppStateScope(
      notifier: widget.appState,
      child: MaterialApp(
        title: 'A&J CITY OASIS',
        debugShowCheckedModeBanner: false,
        theme: buildOasisTheme(),
        home: widget.appState.isBootstrapping
            ? const _SplashScreen()
            : const RootShell(),
      ),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Text(
          'A&J CITY OASIS',
          style: TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w700,
            color: OasisColors.green,
          ),
        ),
      ),
    );
  }
}
