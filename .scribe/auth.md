# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_KEY}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Authenticate by calling <b>POST /api/login</b> and passing the returned token as a Bearer token in the Authorization header.
