# Include INSIDE the existing vsosh.irro.ru location /, beside its proxy_set_header lines.
# Always replace a client-supplied ID. Keep other existing proxy headers.
proxy_set_header X-Request-ID $request_id;
proxy_hide_header X-Request-ID;
add_header X-Request-ID $request_id always;
# Keep the existing inherited access log as well as the correlation log.
access_log /var/log/nginx/access.log combined;
access_log /var/log/nginx/vsosh-request.log vsosh_request;
