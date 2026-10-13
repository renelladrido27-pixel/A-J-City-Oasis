import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/widgets.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

/// Hands a finished file to the phone's "Save to…" screen, where the tenant
/// picks the folder (Downloads, Drive…). Returns false if they backed out.
typedef ReceiptSaver =
    Future<bool> Function({
      required String fileName,
      required String extension,
      required Uint8List bytes,
    });

/// Turns the receipt as it appears on screen into a picture or a PDF.
class ReceiptExport {
  /// The system save dialog — needs no storage permission, since the tenant
  /// chooses the destination themselves.
  static Future<bool> saveWithSystemDialog({
    required String fileName,
    required String extension,
    required Uint8List bytes,
  }) async {
    final path = await FilePicker.saveFile(
      dialogTitle: 'Save receipt',
      fileName: fileName,
      type: FileType.custom,
      allowedExtensions: [extension],
      bytes: bytes,
    );
    return path != null;
  }

  /// A sharp PNG of whatever [boundaryKey]'s RepaintBoundary shows.
  static Future<Uint8List> capturePng(GlobalKey boundaryKey) async {
    final boundary =
        boundaryKey.currentContext!.findRenderObject() as RenderRepaintBoundary;
    // 3x: crisp when zoomed or printed, whatever the phone's own density.
    final image = await boundary.toImage(pixelRatio: 3);
    try {
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      return data!.buffer.asUint8List();
    } finally {
      image.dispose();
    }
  }

  /// A one-page PDF that is exactly the receipt picture — the page is cut to
  /// the picture's shape, so it looks the same as on screen.
  static Future<Uint8List> pdfFromPng(
    Uint8List png, {
    required String title,
  }) async {
    final image = pw.MemoryImage(png);
    const width = 420.0;
    final height = width * image.height! / image.width!;

    final doc = pw.Document(title: title, author: 'A&J CITY OASIS');
    doc.addPage(
      pw.Page(
        pageFormat: PdfPageFormat(width, height),
        margin: pw.EdgeInsets.zero,
        build: (_) => pw.Image(image, fit: pw.BoxFit.fill),
      ),
    );
    return doc.save();
  }
}
