# Listing image classification setup

Listing photos are checked on the server with Google's Gemini Interactions API before the application saves the image or creates the listing. The application sends the temporary uploaded image to Google and accepts it only when the response is exactly `HARDWARE`. Non-hardware, unclear, API-error, and unrecognized responses fail closed.

## Requirements

- A Gemini API key from Google AI Studio, with Gemini API access enabled.
- PHP cURL enabled, or PHP HTTPS streams enabled (`allow_url_fopen=On` and OpenSSL loaded).
- Apache allowed to make outbound HTTPS requests to `generativelanguage.googleapis.com`.

Set `GEMINI_API_KEY` in the Apache process environment before restarting Apache. For a local XAMPP setup, one option is to add this line to the Apache configuration (for example, `apache/conf/extra/httpd-vhosts.conf` inside the relevant virtual host, or `apache/conf/httpd.conf`):

```apache
SetEnv GEMINI_API_KEY "your_gemini_api_key_here"
```

Replace any previous `OPENAI_API_KEY` setting with `GEMINI_API_KEY`. Keep the real key private and out of source control. Do not put it in browser JavaScript or commit it to the project. After setting it, restart Apache. If the key is missing, listings are not created and the seller sees a setup message.

The classifier uses Google's `gemini-3.5-flash-lite` model with inline image data through the Interactions API `v1beta` endpoint. If it returns a temporary overload response, the classifier retries once with `gemini-3.5-flash`. Both models support image input. API usage and rate limits depend on the Google AI Studio project and its current plan. The uploaded image is transmitted to Google for classification.
