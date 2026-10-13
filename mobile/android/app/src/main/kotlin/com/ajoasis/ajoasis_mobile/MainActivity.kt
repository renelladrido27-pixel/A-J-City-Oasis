package com.ajoasis.ajoasis_mobile

import android.app.Activity
import android.content.Intent
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    // A save the Dart side is waiting on while the system "Save to…" screen is open.
    private var pendingSave: MethodChannel.Result? = null
    private var pendingBytes: ByteArray? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        // "saveFile": opens Android's own "Save to…" screen so the tenant picks
        // where a file (a receipt PDF or picture) goes. Because they choose the
        // place themselves, no storage permission is needed. Answers true once
        // written, false if they backed out.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, SAVE_CHANNEL)
            .setMethodCallHandler { call, result ->
                if (call.method != "saveFile") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }
                if (pendingSave != null) {
                    result.error("busy", "Another file is already being saved.", null)
                    return@setMethodCallHandler
                }

                pendingSave = result
                pendingBytes = call.argument<ByteArray>("bytes")

                val intent = Intent(Intent.ACTION_CREATE_DOCUMENT).apply {
                    addCategory(Intent.CATEGORY_OPENABLE)
                    type = call.argument<String>("mimeType") ?: "application/octet-stream"
                    putExtra(Intent.EXTRA_TITLE, call.argument<String>("fileName"))
                }

                try {
                    startActivityForResult(intent, SAVE_REQUEST)
                } catch (e: Exception) {
                    pendingSave = null
                    pendingBytes = null
                    result.error("unavailable", e.message, null)
                }
            }
    }

    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        if (requestCode != SAVE_REQUEST) {
            // Not ours: let the plugins (camera, gallery…) have it.
            super.onActivityResult(requestCode, resultCode, data)
            return
        }

        val result = pendingSave
        val bytes = pendingBytes
        pendingSave = null
        pendingBytes = null

        val uri = data?.data
        if (resultCode != Activity.RESULT_OK || uri == null || bytes == null) {
            result?.success(false)
            return
        }

        try {
            val stream = contentResolver.openOutputStream(uri)
                ?: throw IllegalStateException("The chosen location could not be opened.")
            stream.use { it.write(bytes) }
            result?.success(true)
        } catch (e: Exception) {
            result?.error("write_failed", e.message, null)
        }
    }

    companion object {
        private const val SAVE_CHANNEL = "ajoasis/save_file"
        private const val SAVE_REQUEST = 7401
    }
}
