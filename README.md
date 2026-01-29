# DNS Challenge Utility (for Cloudflare®)

## Introduction

This simple utility is intended to facilitate the creation of wildcard SSL 
certificates, particularly with mod_md.  It supports Cloudflare DNS services.

## Installation

Download the .phar, put it somewhere.  Create /etc/dns-challenge.yml, and
ensure it's readable only by root and the web server user (often www-data).

## Configuration

Add the following configuration to /etc/dns-challenge.yml:
```yaml
dns:
    record_name: _acme-challenge
    record_type: TXT
    record_ttl:  120
cloudflare:
    account: admin@xyz.zcloud
    api_key: [Global API key from cloudflare.com]
```

Or, prefer using an API token:
```yaml
cloudflare:
    api_token: [API token from cloudflare.com]
  ```

Configure apache for mod_md.  It should look something like this:
```apacheconf
<IfModule mod_ssl.c>
	<MDomain xyz.cloud>
		MDMember *.xyz.cloud
	</MDomain>
	MDChallengeDns01 /sbin/dns-challenge --
	MDCertificateAgreement accepted
	MDContactEmail admin@xyz.cloud
	MDCAChallenges dns-01
	<VirtualHost _default_:443>
		ServerAdmin admin@xyz.cloud
		ServerName xyz.cloud
	    ...
	</VirtualHost>
</IfModule>
```
## How it works

When mod_md needs a challenge, it will run the command
  `dns-challenge.phar setup [zone] [challenge]`.

When the challenge is complete and no longer necessary, mod_md will run
`dns-challenge.phar teardown [zone]`.

This software uses the cloudflare API to place and remove the challenge in DNS.

## License

This software is in the public domain.  Included librariers are covered under
their own licenses.  See LICENSE for details.

## Trademark Notice

Cloudflare is a registered trademark of Cloudflare, Inc.
