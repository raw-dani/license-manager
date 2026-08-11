"""
License Client SDK for Python Desktop Applications.

Usage:
    from license_client import LicenseClient, LicenseError

    client = LicenseClient(
        server_url="https://license-server.com",
        api_key="YOUR_API_KEY",
        app_name="MyApp",
    )

    # Activate
    result = client.activate("SP-XXXX-XXXX-XXXX")

    # Verify (call periodically)
    result = client.verify("SP-XXXX-XXXX-XXXX")

    # Deactivate
    result = client.deactivate("SP-XXXX-XXXX-XXXX")

    # Check status
    status = client.status("SP-XXXX-XXXX-XXXX")
"""

from .client import LicenseClient, LicenseError

__all__ = ["LicenseClient", "LicenseError"]
__version__ = "0.1.0"