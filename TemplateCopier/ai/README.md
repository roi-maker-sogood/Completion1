# Gemini API Setup

TemplateCopier uses PHP and Smalot PDF Parser to extract PDF metadata/text, then sends the PDF to Google's Gemini API for visual analysis. No Python runtime or packages are used.

## Steps

1. Create or open a Gemini API key in [Google AI Studio](https://aistudio.google.com/app/apikey). Keep the key private; do not paste it into chat or commit it to the project.
2. Open XAMPP's Apache configuration file, usually `C:\xampp\apache\conf\httpd.conf`.
3. Add these directives to the Apache configuration and replace the placeholder with your key:

	```apache
	SetEnv GEMINI_API_KEY "paste-your-key-here"
	SetEnv GEMINI_MODEL "gemini-2.5-flash"
	SetEnv GEMINI_FALLBACK_MODEL "gemini-2.5-flash-lite"
	```

	Use a real Gemini model name from Google AI Studio. `GEMINI_MODEL` is optional; if omitted, the PHP code uses `gemini-2.5-flash`. If that model returns HTTP 503 after retries, the app tries `GEMINI_FALLBACK_MODEL`, which defaults to `gemini-2.5-flash-lite`. Set the fallback value to an empty string to disable it. Restart Apache after changing these settings.

	If the app returns `Gemini API error (401)`, the key is missing, invalid, expired, or not enabled for Gemini. Recreate the key in Google AI Studio, update `GEMINI_API_KEY`, and restart Apache.
4. In XAMPP Control Panel, restart Apache so it receives the new settings.
5. Open `http://localhost/Login/TemplateCopier/` and upload a PDF no larger than 10 MB.
6. When Gemini finishes, edit the SVG design by dragging elements or changing their properties, then select **Save changes**.
7. Select **Download JSON** to export the editable blueprint. It is also stored in `TemplateCopier/uploads/blueprints/`.

## PHP Requirements

- Enable the PHP cURL extension in the active XAMPP `php.ini` file.
- Set `upload_max_filesize=10M`, `post_max_size=12M`, `memory_limit=512M`, and `max_execution_time=300` in that file if the current limits are lower.
- Restart Apache after changing `php.ini`.

Gemini receives the complete uploaded PDF for analysis. Detected images are currently editable placeholders with visual descriptions; their original pixels are not extracted into separate image files yet.