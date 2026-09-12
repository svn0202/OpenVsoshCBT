#!/usr/bin/env python3
"""Render a deployment's security.txt using an operator-supplied public contact."""
import argparse
import datetime
from pathlib import Path
from urllib.parse import urlsplit


def render(contact, expires):
    uri = urlsplit(contact)
    if '\r' in contact or '\n' in contact or uri.scheme not in ('mailto', 'https'):
        raise ValueError('Contact must be a single mailto: or https: URI')
    if (uri.scheme == 'mailto' and ('@' not in uri.path or uri.query)) or (uri.scheme == 'https' and not uri.netloc):
        raise ValueError('Contact URI is incomplete')
    expiry = datetime.datetime.fromisoformat(expires.replace('Z', '+00:00'))
    now = datetime.datetime.now(datetime.timezone.utc)
    if expiry.tzinfo is None or not now < expiry <= now + datetime.timedelta(days=366):
        raise ValueError('Expires must have a timezone and be within the next year')
    return 'Contact: ' + contact + '\nExpires: ' + expiry.astimezone(datetime.timezone.utc).isoformat().replace('+00:00', 'Z') + '\nPreferred-Languages: ru, en\n'


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--contact', required=True)
    parser.add_argument('--expires', required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    try:
        content = render(args.contact, args.expires)
    except ValueError as error:
        parser.error(str(error))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(content, encoding='utf-8')
