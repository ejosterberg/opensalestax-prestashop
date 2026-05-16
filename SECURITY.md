# Security policy

## Reporting a vulnerability

Email **ejosterberg@gmail.com** with subject prefixed
`[opensalestax-prestashop SECURITY]`. Please include:

- A description of the vulnerability and its impact.
- Reproduction steps (or a proof-of-concept).
- Affected versions if known.
- Whether you'd like public credit in the eventual fix
  changelog (default: yes, with your name; opt out and we'll
  credit "an external reporter").

Please do **not** file public GitHub issues for security
reports. We aim to acknowledge within 72 hours and ship a fix
on a timeline proportionate to severity.

## Threat model summary

The module loads in the merchant's PrestaShop process. The
engine call is OUTBOUND only — no inbound webhook surface, no
JWT / token verification, no port to expose.

The dominant attack class is **SSRF via the admin-controlled
engine base URL**: an attacker who escalates to admin (any
admin-side vuln in PrestaShop or another module) could
otherwise direct our cURL handle at internal services
(Redis, intranet, cloud metadata at `169.254.169.254`).

Mitigations in v0.1:

- `UrlValidator` rejects RFC1918, loopback, link-local
  (including the cloud metadata endpoint), CGNAT, and
  multicast IPs by default. Merchants self-hosting on a
  private LAN must explicitly opt in via the
  **Allow private-network engines** toggle.
- TLS verify is on by default; disabling it requires a
  deliberate admin action.
- No customer PII or credentials are ever logged. Structured
  warnings carry numeric metadata (status, RTT, line count)
  only.

DNS-rebinding mitigation (cURL `CURLOPT_RESOLVE` IP-pinning,
mirrors OpenCart v0.2.0) is **on the v0.2 roadmap**. v0.1
relies on the save-time SSRF check; an attacker who controls
DNS for the configured hostname can swap the resolution
between save and request time. Merchants who consider this
threat material should pin the engine via `/etc/hosts` /
network policy until v0.2 lands.

## Out of scope

- Vulnerabilities in PrestaShop core (report to
  `https://github.com/PrestaShop/PrestaShop`).
- Vulnerabilities in the OpenSalesTax engine itself (report
  to the engine's repo).
- Vulnerabilities in third-party PrestaShop modules running
  alongside this one.
