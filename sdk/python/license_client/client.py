"""
License Client for Python Desktop Applications.

Handles hardware fingerprinting, activation, verification, and offline grace period.
"""

import hashlib
import json
import os
import platform
import socket
import subprocess
import sys
import tempfile
import time
from pathlib import Path

import requests


class LicenseError(Exception):
    """Exception raised for license-related errors."""

    def __init__(self, message: str, code: int = None):
        super().__init__(message)
        self.code = code


class LicenseClient:
    """
    License client for desktop applications.

    Args:
        server_url: License server base URL (e.g., https://license-server.com)
        api_key: API key for the license server
        app_name: Application name (used for storing local state)
        config_dir: Directory to store license data (default: user config dir)
    """

    def __init__(
        self,
        server_url: str,
        api_key: str,
        app_name: str = "MyApp",
        config_dir: str = None,
    ):
        self.server_url = server_url.rstrip("/")
        self.api_key = api_key
        self.app_name = app_name

        if config_dir:
            self.config_dir = Path(config_dir)
        else:
            base_dir = Path(os.environ.get(
                "APPDATA",
                str(Path.home() / ".config"),
            ))
            self.config_dir = base_dir / app_name

        self.config_dir.mkdir(parents=True, exist_ok=True)
        self.state_file = self.config_dir / "license_state.json"
        self.token = None
        self._load_state()

    def _load_state(self):
        """Load saved license state."""
        if self.state_file.exists():
            try:
                data = json.loads(self.state_file.read_text())
                self.token = data.get("token")
            except (json.JSONDecodeError, OSError):
                pass

    def _save_state(self):
        """Save license state."""
        data = {"token": self.token}
        self.state_file.write_text(json.dumps(data, indent=2))

    def _clear_state(self):
        """Clear license state."""
        if self.state_file.exists():
            self.state_file.unlink()
        self.token = None

    def generate_fingerprint(self) -> str:
        """Generate a hardware fingerprint from CPU, MAC, and disk serial."""
        identifiers = [
            self._get_cpu_id(),
            self._get_mac_address(),
            self._get_disk_serial(),
            self._get_motherboard_serial(),
            platform.node(),
        ]

        raw = "|".join([x for x in identifiers if x])
        if not raw:
            raise LicenseError("Could not generate hardware fingerprint")

        return hashlib.sha512(raw.encode()).hexdigest()

    def _get_cpu_id(self) -> str:
        """Get CPU identifier (platform-specific)."""
        try:
            if sys.platform == "win32":
                result = subprocess.run(
                    ["wmic", "cpu", "get", "ProcessorId"],
                    capture_output=True, text=True, timeout=5,
                )
                lines = result.stdout.strip().split("\n")
                if len(lines) > 1:
                    return lines[1].strip()
            elif sys.platform == "darwin":
                result = subprocess.run(
                    ["sysctl", "-n", "machdep.cpu.brand_string"],
                    capture_output=True, text=True, timeout=5,
                )
                return result.stdout.strip()
            else:  # Linux
                result = subprocess.run(
                    ["cat", "/proc/cpuinfo"],
                    capture_output=True, text=True, timeout=5,
                )
                for line in result.stdout.split("\n"):
                    if line.startswith("Serial"):
                        return line.split(":")[1].strip()
                for line in result.stdout.split("\n"):
                    if line.startswith("model name"):
                        return line.split(":")[1].strip()
        except (subprocess.SubprocessError, FileNotFoundError):
            pass
        return ""

    def _get_mac_address(self) -> str:
        """Get the primary MAC address."""
        try:
            if sys.platform == "win32":
                result = subprocess.run(
                    ["getmac", "/FO", "CSV", "/NH"],
                    capture_output=True, text=True, timeout=5,
                )
                lines = result.stdout.strip().split("\n")
                if lines:
                    parts = lines[0].split(",")
                    if len(parts) > 0:
                        return parts[0].strip('"')
            else:
                result = subprocess.run(
                    ["cat", "/sys/class/net/eth0/address"],
                    capture_output=True, text=True, timeout=5,
                )
                mac = result.stdout.strip()
                if mac:
                    return mac
        except (subprocess.SubprocessError, FileNotFoundError):
            pass
        return ""

    def _get_disk_serial(self) -> str:
        """Get disk serial number (platform-specific)."""
        try:
            if sys.platform == "win32":
                result = subprocess.run(
                    ["wmic", "diskdrive", "get", "SerialNumber"],
                    capture_output=True, text=True, timeout=5,
                )
                lines = result.stdout.strip().split("\n")
                if len(lines) > 1:
                    return lines[1].strip()
            elif sys.platform == "darwin":
                result = subprocess.run(
                    ["system_profiler", "SPStorageDataType"],
                    capture_output=True, text=True, timeout=10,
                )
                if result.returncode == 0:
                    for line in result.stdout.split("\n"):
                        if "Serial Number" in line:
                            return line.split(":")[1].strip()
            else:  # Linux
                result = subprocess.run(
                    ["lsblk", "-no", "SERIAL"],
                    capture_output=True, text=True, timeout=5,
                )
                lines = [l.strip() for l in result.stdout.split("\n") if l.strip()]
                if lines:
                    return lines[0]
        except (subprocess.SubprocessError, FileNotFoundError):
            pass
        return ""

    def _get_motherboard_serial(self) -> str:
        """Get motherboard serial number."""
        try:
            if sys.platform == "win32":
                result = subprocess.run(
                    ["wmic", "baseboard", "get", "SerialNumber"],
                    capture_output=True, text=True, timeout=5,
                )
                lines = result.stdout.strip().split("\n")
                if len(lines) > 1:
                    return lines[1].strip()
            elif sys.platform == "darwin":
                result = subprocess.run(
                    ["system_profiler", "SPHardwareDataType"],
                    capture_output=True, text=True, timeout=10,
                )
                if result.returncode == 0:
                    for line in result.stdout.split("\n"):
                        if "Serial Number" in line:
                            return line.split(":")[1].strip()
        except (subprocess.SubprocessError, FileNotFoundError):
            pass
        return ""

    def _request(self, method: str, endpoint: str, data: dict = None) -> dict:
        """Make an API request to the license server."""
        url = f"{self.server_url}{endpoint}"
        headers = {
            "X-API-Key": self.api_key,
            "Accept": "application/json",
        }

        if self.token:
            headers["X-Authorization"] = self.token

        try:
            if method == "GET":
                response = requests.get(url, headers=headers, params=data, timeout=10)
            else:
                response = requests.post(url, headers=headers, json=data, timeout=10)
        except requests.RequestException as e:
            raise LicenseError(f"Connection error: {str(e)}", code=0)

        try:
            result = response.json()
        except ValueError:
            raise LicenseError(f"Invalid server response: {response.status_code}", code=response.status_code)

        return result

    def activate(self, license_key: str) -> dict:
        """Activate a license for this device."""
        fingerprint = self.generate_fingerprint()

        result = self._request("POST", "/api/v1/activate", {
            "license_key": license_key,
            "fingerprint": fingerprint,
            "platform": "desktop",
            "device_info": {
                "hostname": platform.node(),
                "os": platform.system(),
                "os_version": platform.release(),
                "machine": platform.machine(),
                "python_version": platform.python_version(),
            },
        })

        if result.get("status") == "success":
            data = result.get("data", {})
            self.token = data.get("token")
            self._save_state()
            return data

        raise LicenseError(
            result.get("message", "Activation failed"),
            code=result.get("code"),
        )

    def verify(self, license_key: str) -> dict:
        """Verify the license periodically."""
        fingerprint = self.generate_fingerprint()

        result = self._request("POST", "/api/v1/verify", {
            "license_key": license_key,
            "fingerprint": fingerprint,
            "platform": "desktop",
            "device_info": {
                "hostname": platform.node(),
                "os": platform.system(),
            },
        })

        if result.get("status") == "success":
            data = result.get("data", {})
            self.token = data.get("token")
            self._save_state()
            return data

        raise LicenseError(
            result.get("message", "Verification failed"),
            code=result.get("code"),
        )

    def deactivate(self, license_key: str) -> dict:
        """Deactivate the license for this device."""
        fingerprint = self.generate_fingerprint()

        result = self._request("POST", "/api/v1/deactivate", {
            "license_key": license_key,
            "fingerprint": fingerprint,
            "platform": "desktop",
        })

        if result.get("status") == "success":
            self._clear_state()
            return result

        raise LicenseError(
            result.get("message", "Deactivation failed"),
            code=result.get("code"),
        )

    def status(self, license_key: str) -> dict:
        """Check the license status."""
        result = self._request("GET", f"/api/v1/license/{license_key}")
        return result

    def ping(self) -> dict:
        """Check server connectivity."""
        return self._request("POST", "/api/v1/ping", {})


def main():
    """CLI usage example."""
    import argparse

    parser = argparse.ArgumentParser(description="License Client CLI")
    parser.add_argument("command", choices=["activate", "verify", "deactivate", "status", "ping"])
    parser.add_argument("license_key", nargs="?", help="License key")
    parser.add_argument("--server", required=True, help="License server URL")
    parser.add_argument("--api-key", required=True, help="License API key")
    parser.add_argument("--app", default="MyApp", help="Application name")

    args = parser.parse_args()

    client = LicenseClient(
        server_url=args.server,
        api_key=args.api_key,
        app_name=args.app,
    )

    try:
        if args.command == "activate":
            result = client.activate(args.license_key)
            print(json.dumps(result, indent=2))
        elif args.command == "verify":
            result = client.verify(args.license_key)
            print(json.dumps(result, indent=2))
        elif args.command == "deactivate":
            result = client.deactivate(args.license_key)
            print(json.dumps(result, indent=2))
        elif args.command == "status":
            result = client.status(args.license_key)
            print(json.dumps(result, indent=2))
        elif args.command == "ping":
            result = client.ping()
            print(json.dumps(result, indent=2))
    except LicenseError as e:
        print(f"Error: {e.message}")
        sys.exit(1)


if __name__ == "__main__":
    main()