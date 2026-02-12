Sí, Ricardo Aníbal—eso explica el fallo.

Traefik está intentando emitir un wildcard *.laruta11.cl, pero Let’s Encrypt solo permite wildcard con DNS-01 challenge; tu Traefik está configurado sin solver DNS y por eso sale “could not determine solvers”.

Soluciones:

No uses wildcard: en Easypanel/Traefik configura certificados por dominio exacto (app.laruta11.cl, laruta11.cl, etc.) y desactiva la regla HostRegexp(^.+\.laruta11.cl$) si viene de la plantilla.
Si necesitas wildcard, tienes que configurar en Traefik un DNS provider (DNS-01) con API keys del proveedor DNS de laruta11.cl (Cloudflare/u otro). Dime qué DNS usas para laruta11.cl y te digo exactamente qué parámetros/variables agregar en Easypanel/Traefik.


esto no sale nada root@srv1331519:# docker service inspect websites_app3 | grep -i traefik | head -n 50
root@srv1331519:#
Kodee
Ricardo Aníbal, correcto: si docker service inspect websites_app3 | grep -i traefik no devuelve nada, ese servicio no está siendo gestionado por Traefik (no tiene labels), así que no puede emitir SSL para app.laruta11.cl.

Pero el error que sí vimos de Traefik es para otro router: https-websites_landing-r11-1@file intentando un wildcard *.laruta11.cl (y fallando por no tener DNS-01).

Haz esto en Easypanel (rápido):

En el proyecto websites → landing-r11 (o el que tenga el dominio laruta11), cambia la regla para que use dominios explícitos (por ejemplo laruta11.cl y www.laruta11.cl) y **elimina cualquier “Wildcard / HostRegexp / *.laruta11.cl”**.
En websites → app3, asigna el dominio app.laruta11.cl y vuelve a Deploy.
Verifica que ahora sí haya labels:
docker service inspect websites_app3 | grep -i "traefik\\."
Si me dices el nombre exacto del website/proyecto que debería servir app.laruta11.cl dentro de Easypanel (app3 u otro), te indico dónde tocarlo para que genere las labels correctas.


https://easy.agenterag.com/projects/websites/app/app3/domains http://websites-app3.dj3bvg.easypanel.host
Kodee
Ricardo Aníbal, perfecto: websites-app3.dj3bvg.easypanel.host funciona porque lo sirve el proxy interno de Easypanel, pero para app.laruta11.cl necesitas que Easypanel genere labels para Traefik (ahora websites_app3 está sin labels).

Haz esto en Easypanel → websites → app3 → Domains:

Add domain: app.laruta11.cl
Activa HTTPS / Let’s Encrypt para ese dominio.
Deploy (redeploy) del servicio.
Luego confirma desde la VPS:

docker service inspect websites_app3 | grep -i "traefik\\."
docker service logs traefik --since 30m | egrep -i "app\\.laruta11\\.cl|acme|letsencrypt|error|challenge" | tail -n 80
Si después de redeploy siguen sin aparecer labels, el problema es que app3 está creado como “internal app” y no como “public via Traefik”; en ese caso dime qué opción te muestra en esa pantalla (Proxy/Ingress/Expose) y lo ajustamos.


root@srv1331519:~# docker service inspect websites_app3 | grep -i "traefik\."
docker service logs traefik --since 30m | egrep -i "app\.laruta11\.cl|acme|letsencrypt|error|challenge" | tail -n 80
traefik.1.r3qssm4d8e51@srv1331519 | 2026-02-12T12:09:19Z ERR Unable to obtain ACME certificate for domains error="unable to generate a certificate for the domains [app.laruta11.cl]: error: one or more domains had a problem:\n[app.laruta11.cl] invalid authorization: acme: error: 403 :: urn:ietf:params:acme:error:unauthorized :: 2a02:4780:13:1538:0:3921:ef81:4: Invalid response from http://app.laruta11.cl/.well-known/acme-challenge/Tlp-Rvj7_JbxyqBAgNbAxAN_oeMHgrrfN77dfro21wE : 404\n" ACME CA= https://acme-v02.api.letsencrypt.org/directory acmeCA= https://acme-v02.api.letsencrypt.org/directory domains=["app.laruta11.cl"] providerName=letsencrypt.acme routerName=https-websites_app3-1@file rule="Host(app.laruta11.cl) && PathPrefix(/)"
traefik.1.r3qssm4d8e51@srv1331519 | 181.200.20.243 - -
Kodee
Ricardo Aníbal, ahora Traefik sí está intentando emitir el certificado, pero Let’s Encrypt falla porque al validar por HTTP-01 recibe 404 en:

http://app.laruta11.cl/.well-known/acme-challenge/...

Eso casi siempre significa que app.laruta11.cl no está apuntando al VPS correcto (o está yendo por IPv6 a otro lado), o que hay otra app respondiendo ese host.

Verifícalo así:

curl -I http://app.laruta11.cl/.well-known/acme-challenge/test
curl -I http://76.13.126.63/.well-known/acme-challenge/test -H "Host: app.laruta11.cl"
Y en DNS (imprescindible):

app.laruta11.cl A → 76.13.126.63
si tienes AAAA para app, elimínalo o asegúrate que apunte a la IPv6 de tu VPS (2a02:4780:4:3576::1).
Dime qué te devuelven esos 2 curl -I y confirmo si es DNS/AAAA o un enrutamiento equivocado en Traefik.


root@srv1331519:~# curl -I http://app.laruta11.cl/.well-known/acme-challenge/test
curl -I http://76.13.126.63/.well-known/acme-challenge/test -H "Host: app.laruta11.cl"
HTTP/1.1 404 Not Found
Connection: Keep-Alive
Keep-Alive: timeout=5, max=100
Content-Type: text/html
Content-Length: 150
Date: Thu, 12 Feb 2026 12:15:15 GMT
Server: LiteSpeed
platform: hostinger
panel: hpanel
Content-Security-Policy: upgrade-insecure-requests

HTTP/1.1 404 Not Found
Date: Thu, 12 Feb 2026 12:15:12 GMT

root@srv1331519:~#
Kodee
Ricardo Aníbal, está clarísimo: app.laruta11.cl (y hasta pegándole directo a 76.13.126.63 con Host: app.laruta11.cl) responde Server: LiteSpeed / platform: hostinger, o sea no está entrando a Traefik/Easypanel; algún servicio en la VPS (LiteSpeed / stack anterior) está agarrando los puertos 80/443 o hay un proxy fuera de Traefik.

En la VPS ejecuta y pégame la salida:

ss -lntp | grep -E ':80|:443'
docker ps --format 'table {{.Names}}\t{{.Ports}}\t{{.Image}}'
systemctl status lsws --no-pager
systemctl status openlitespeed --no-pager
Con eso te digo exactamente qué proceso está ocupando 80/443 y cómo liberarlo para que Traefik pueda servir /.well-known/acme-challenge/ y emitir el SSL.


root@srv1331519:~# ss -lntp | grep -E ':80|:443'
docker ps --format 'table {{.Names}}\t{{.Ports}}\t{{.Image}}'
systemctl status lsws --no-pager
systemctl status openlitespeed --no-pager
LISTEN 0 4096 0.0.0.0:80 0.0.0.0:* users:(("docker-proxy",pid=250381,fd=7))
LISTEN 0 4096 0.0.0.0:443 0.0.0.0:* users:(("docker-proxy",pid=250403,fd=7))
LISTEN 0 4096 [::]:80 [::]:* users:(("docker-proxy",pid=250386,fd=7))
LISTEN 0 4096 [::]:443 [::]:* users:(("docker-proxy",pid=250409,fd=7))
NAMES PORTS IMAGE
websites_landing-r11.1.vlc589bwhouqjyjyl8gyv3j8a easypanel/websites/landing-r11:latest
websites_app3.1.0wbch3pk
Kodee
Ricardo Aníbal, los puertos 80/443 sí están tomados por Docker (bien), pero tus curl siguen llegando a LiteSpeed → eso pasa cuando el DNS de app.laruta11.cl apunta a otro servidor/IP (probablemente el Business Hosting), o cuando el cliente resuelve a otra IP (A/AAAA).

Verifica y corrige DNS (en el proveedor DNS donde gestionas laruta11.cl):

app A → 76.13.126.63
app AAAA → 2a02:4780:4:3576::1 (o elimínalo si no usarás IPv6)
elimina cualquier A/AAAA viejo.
Confírmalo desde la VPS con:

dig +short app.laruta11.cl A
dig +short app.laruta11.cl AAAA
Cuando esos dig devuelvan 76.13.126.63 (y/o la IPv6 correcta), vuelve a intentar el SSL en Easypanel y debería emitir sin el 404 del challenge.


websites_landing-r11.1.vlc589bwhouqjyjyl8gyv3j8a easypanel/websites/landing-r11:latest
websites_app3.1.0wbch3pk5mxo9eaklrqwh4c0j 80/tcp easypanel/websites/app3:latest
websites_caja3.1.piqmp9sdr256p1xh17olsuunf 80/tcp easypanel/websites/caja3:latest
websites_landing3.1.gwklu8mtyscq007jnw3bo8swn 80/tcp easypanel/websites/landing3:latest
websites_caja-r11.1.e7cps2io318x4eh5u2xn99epn 80/tcp, 3000/tcp easypanel/websites/caja-r11:latest
traefik.1.r3qssm4d8e51fjts7mxjv2mj9 0.0.0.0:80->80/tcp, [::]:80->80/tcp, 0.0.0.0:443->
Kodee
Ricardo Aníbal, perfecto: Traefik está publicado en 80/443 y los sitios internos no, así que el problema ya no es puertos; es DNS (porque tus curl siguen viendo LiteSpeed/Hostinger, que no es Traefik).

Ejecuta y pégame esto para confirmar a qué IP está resolviendo ahora mismo:

dig +short app.laruta11.cl A
dig +short app.laruta11.cl AAAA
Luego, en el DNS de laruta11.cl, deja:

app A → 76.13.126.63
app AAAA → 2a02:4780:4:3576::1 (o bórralo)
Cuando el dig muestre esas IPs, reintenta Let’s Encrypt en Easypanel y el challenge ya no debería dar 404.


Nameservers
Los servidores de nombres manejan las solicitudes de Internet para tu dominio. Puedes utilizar servidores de nombres de Hostinger o utilizar servidores de nombres personalizados para apuntar a otro proveedor de hosting.
ns1.dns-parking.com

ns2.dns-parking.com
Administrar registros DNS
Estos registros definen cómo se comporta tu dominio. Los usos comunes incluyen apuntar tu dominio a servidores web o configurar la entrega de email para tu dominio.
Tipo
Tipo

Nombre
Nombre

Apunta a

TTL
TTL

Agregar registro

Tipo
Nombre
Prioridad
Contenido
TTL
AAAA test 0 2a02:4780:13:1538:0:3921:ef81:4 1800
Borrar

Editar
AAAA app 0 2a02:4780:13:1538:0:3921:ef81:4 1800
Borrar

Editar
AAAA caja 0 2a02:4780:13:1538:0:3921:ef81:4 1800
Borrar

Editar
AAAA @ 0 2a02:4780:13:1538:0:3921:ef81:4 1800
Borrar

Editar
Kodee
Ricardo Aníbal, el problema está en tu DNS: app tiene AAAA apuntando a 2a02:4780:13:1538:…:ef81:4, pero tu VPS IPv6 correcta es 2a02:4780:4:3576::1. Let’s Encrypt está validando por IPv6 y llega a otro servidor → por eso el 404.

Cámbialo así en laruta11.cl (DNS):

AAAA app → borrar (recomendado), o actualizarlo a 2a02:4780:4:3576::1.
(Opcional) revisa también AAAA @ y caja porque están apuntando al mismo IPv6 incorrecto.
Si quieres, te lo dejo listo yo: ¿confirmas que elimine el registro AAAA app en laruta11.cl?


entonces cambio contenido a 2a02:4780:4:3576::1
Kodee
Sí, Ricardo Aníbal: cambia el AAAA de app a 2a02:4780:4:3576::1 (TTL puede quedar en 1800).

Luego valida y reintenta SSL:

dig +short app.laruta11.cl AAAA
curl -I -g "http://[2a02:4780:4:3576::1]/.well-known/acme-challenge/test" -H "Host: app.laruta11.cl"
docker service logs traefik --since 15m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 80
Si el dig aún muestra la IPv6 vieja, es solo propagación/caché; cuando cambie, Let’s Encrypt debería emitir el certificado sin ese 404.


o borro los AAAA?
Kodee
Sí, Ricardo Aníbal: si no necesitas IPv6, borra los AAAA (al menos app, y también @/caja/test si quieres evitar que el tráfico vaya por esa IPv6 incorrecta). Con solo A → 76.13.126.63, Let’s Encrypt validará por IPv4 y el SSL suele salir al instante.

Si prefieres mantener IPv6, entonces no los borres: corrígelos para que apunten a la IPv6 real de tu VPS (2a02:4780:4:3576::1).


OK QUEDA ASI. Tipo
Nombre
Prioridad
Contenido
TTL
AAAA test 0 2a02:4780:4:3576::1 1800
Borrar

Editar
AAAA app 0 2a02:4780:4:3576::1 1800
Borrar

Editar
AAAA caja 0 2a02:4780:4:3576::1 1800
Borrar

Editar
AAAA @ 0 2a02:4780:4:3576::1 1800
Borrar

Editar
Kodee
Perfecto, Ricardo Aníbal: ahora los AAAA ya apuntan a la IPv6 correcta del VPS.

Espera a que propague y valida desde la VPS:

dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl/.well-known/acme-challenge/test
docker service logs traefik --since 20m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 80
Cuando el curl deje de mostrar LiteSpeed y responda Traefik (aunque sea 404/503 distinto), vuelve a activar Let’s Encrypt en Easypanel para app.laruta11.cl.


Nameservers
Los servidores de nombres manejan las solicitudes de Internet para tu dominio. Puedes utilizar servidores de nombres de Hostinger o utilizar servidores de nombres personalizados para apuntar a otro proveedor de hosting.
ns1.dns-parking.com

ns2.dns-parking.com
Administrar registros DNS
Estos registros definen cómo se comporta tu dominio. Los usos comunes incluyen apuntar tu dominio a servidores web o configurar la entrega de email para tu dominio.
Tipo
Tipo

Nombre
Nombre

Apunta a

TTL
TTL

Agregar registro

Tipo
Nombre
Prioridad
Contenido
TTL
CNAME hostingermail-c._domainkey 0 hostingermail-c.dkim.mail.hostinger.com 300
Borrar

Editar
CNAME hostingermail-b._domainkey 0 hostingermail-b.dkim.mail.hostinger.com 300
Borrar

Editar
CNAME hostingermail-a._domainkey 0 hostingermail-a.dkim.mail.hostinger.com 300
Borrar

Editar
A www 0 76.13.126.63 14400
Borrar

Editar
AAAA test 0 2a02:4780:4:3576::1 1800
Borrar

Editar
A test 0 82.25.67.153 1800
Borrar

Editar
CNAME autod
Kodee
Ricardo Aníbal, bien por los AAAA, pero todavía veo un conflicto: test tiene A → 82.25.67.153 (tu Business Hosting) y AAAA → 2a02:4780:4:3576::1 (tu VPS). Eso hace que a veces cargue un servidor y a veces el otro.

Para evitar comportamientos raros durante SSL/migración, deja cada host apuntando a un solo destino (A y AAAA del mismo servidor). Por ejemplo, si test.laruta11.cl debe ir al VPS, cambia su A a 76.13.126.63:

dig +short test.laruta11.cl A
dig +short test.laruta11.cl AAAA
Si me confirmas “sí, test debe ir al VPS”, te lo ajusto yo cambiando A test → 76.13.126.63.


lo elimine
Kodee
Perfecto, Ricardo Aníbal. Ahora valida que ya no exista ese A viejo y que app resuelva bien:

dig +short test.laruta11.cl A
dig +short app.laruta11.cl A
dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl/.well-known/acme-challenge/test
Si el curl ya no muestra LiteSpeed, vuelve a activar Let’s Encrypt en Easypanel para app.laruta11.cl y debería emitir sin errores.


ok que hago ahora
Kodee
Ricardo Aníbal, ahora toca comprobar resolución DNS y luego emitir el SSL en Easypanel.

En la VPS verifica que ya apunta al VPS:
dig +short app.laruta11.cl A
dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl
Si dig devuelve 76.13.126.63 y 2a02:4780:4:3576::1, entra a Easypanel → tu website app3 (o el que corresponda) → Domains → agrega app.laruta11.cl → Enable SSL (Let’s Encrypt).
Si falla, pégame el error de Easypanel y esta salida:
docker service logs traefik --since 30m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 120


root@srv1331519:~# dig +short app.laruta11.cl A
dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl
76.13.126.63
2a02:4780:13:1538:0:3921:ef81:4
HTTP/1.1 301 Moved Permanently
Connection: Keep-Alive
Keep-Alive: timeout=5, max=100
Date: Thu, 12 Feb 2026 12:36:54 GMT
Server: LiteSpeed
Location: https://app.laruta11.cl/
platform: hostinger
panel: hpanel
Content-Security-Policy: upgrade-insecure-requests

root@srv1331519:~#
Kodee
Ricardo Aníbal, todavía NO propagó: el AAAA de app.laruta11.cl sigue resolviendo a 2a02:4780:13:1538:… (viejo) y por eso estás llegando a LiteSpeed, no a tu VPS/Easypanel.

Haz esto ahora:

En DNS, revisa que AAAA app esté exactamente en 2a02:4780:4:3576::1 (y no haya otro AAAA duplicado en otro panel/plantilla).
Para forzar que no use IPv6 mientras propaga, borra temporalmente el AAAA de app y deja solo:
app A → 76.13.126.63
Vuelve a comprobar:

dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl
Cuando el dig AAAA quede vacío (o muestre 2a02:4780:4:3576::1) y el Server: ya no sea LiteSpeed, recién ahí activa Let’s Encrypt en Easypanel.


root@srv1331519:~# dig +short app.laruta11.cl AAAA
curl -I http://app.laruta11.cl
2a02:4780:13:1538:0:3921:ef81:4
HTTP/1.1 301 Moved Permanently
Connection: Keep-Alive
Keep-Alive: timeout=5, max=100
Date: Thu, 12 Feb 2026 12:40:47 GMT
Server: LiteSpeed
Location: https://app.laruta11.cl/
platform: hostinger
panel: hpanel
Content-Security-Policy: upgrade-insecure-requests
Kodee
Ricardo Aníbal, ese AAAA viejo sigue publicado en la zona DNS activa, por eso siempre cae en LiteSpeed.

Yo en tu DNS todavía veo AAAA app → 2a02:4780:13:1538:0:3921:ef81:4 (no el nuevo). Lo más rápido para desbloquear SSL es eliminar el AAAA de app y dejar solo el A al VPS.

¿Confirmas que elimine el registro AAAA app de laruta11.cl ahora?


discrepo haha CNAME hostingermail-c._domainkey 0 hostingermail-c.dkim.mail.hostinger.com 300
Borrar

Editar
CNAME hostingermail-b._domainkey 0 hostingermail-b.dkim.mail.hostinger.com 300
Borrar

Editar
CNAME hostingermail-a._domainkey 0 hostingermail-a.dkim.mail.hostinger.com 300
Borrar

Editar
A www 0 76.13.126.63 14400
Borrar

Editar
CNAME autodiscover 0 autodiscover.mail.hostinger.com 300
Borrar

Editar
A ftp 0 82.25.67.153 1800
Borrar

Editar
A app 0 76.13.126.63 1800
Borrar

Editar
CNAME autoconfig 0 autoconfig.mail.hostinger.com 300
Borrar

Editar
TXT _dmarc 0 "v=DMARC1; p=none" 3600
Borrar

Editar
A caja 0 82.25.67.153 1800
Borrar

Editar
A caja2 0 76.13.126.63 14400
Borrar

Editar
A api-go-caja-r11 0 76.13.126.63 14400
Borrar

Editar
CAA @ 0 0 issue "globalsign.com" 14400
Borrar

Editar
CAA @ 0 0 issue "comodoca.com" 14400
Borrar

Editar
CAA @ 0 0 issue "digicert.com" 14400
Borrar

Editar
CAA @ 0 0 issue "letsencrypt.org" 14400
Borrar

Editar
CAA @ 0 0 issue "pk
Kodee
Ricardo Aníbal, tienes razón: en la zona DNS de Hostinger no existe ningún AAAA para app; solo está el A → 76.13.126.63. Si dig te sigue devolviendo un AAAA viejo, viene de caché/propagación o de DNS externo (Cloudflare/ISP/otro resolver).

Haz esto en la VPS para confirmar contra resolvers públicos:

dig @1.1.1.1 app.laruta11.cl AAAA +short
dig @8.8.8.8 app.laruta11.cl AAAA +short
dig @9.9.9.9 app.laruta11.cl AAAA +short
Si en esos ya no aparece AAAA, entonces el siguiente paso es emitir SSL en Easypanel; si aún aparece, dime qué te devuelven esos 3 dig y lo seguimos.


root@srv1331519:# dig @1.1.1.1 app.laruta11.cl AAAA +short
dig @8.8.8.8 app.laruta11.cl AAAA +short
dig @9.9.9.9 app.laruta11.cl AAAA +short
root@srv1331519:#
Kodee
Perfecto, Ricardo Aníbal: eso significa que ya no hay AAAA público para app.laruta11.cl (bien).

Ahora fuerza prueba por IPv4 y luego emite el SSL:

curl -4I http://app.laruta11.cl
curl -4I http://app.laruta11.cl/.well-known/acme-challenge/test
Después, en Easypanel → tu app/website → Domains → app.laruta11.cl → Enable SSL (Let’s Encrypt).

Si aún saliera error, pégame:

docker service logs traefik --since 30m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 150


root@srv1331519:~# curl -4I http://app.laruta11.cl
curl -4I http://app.laruta11.cl/.well-known/acme-challenge/test
HTTP/1.1 308 Permanent Redirect
Location: https://app.laruta11.cl/
Date: Thu, 12 Feb 2026 12:44:17 GMT
Content-Length: 18

HTTP/1.1 404 Not Found
Date: Thu, 12 Feb 2026 12:44:17 GMT

root@srv1331519:~#
Kodee
Ricardo Aníbal, perfecto: ya estás llegando a tu VPS (308 de Traefik y el /.well-known/... responde desde tu stack, no LiteSpeed).

Ahora ve a Easypanel → tu app/website → Domains → app.laruta11.cl → Enable SSL (Let’s Encrypt).

Si falla, pega el error y ejecuta:

docker service logs traefik --since 30m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 200


y si ya estaba escrito esto Ahora ve a Easypanel → tu app/website → Domains → app.laruta11.cl → Enable SSL (Let’s Encrypt).
Kodee
Ricardo Aníbal, sí: ese es exactamente el siguiente paso ahora que app.laruta11.cl ya responde desde el VPS por IPv4.

Si al activarlo se queda “pending” o da error, pégame el mensaje exacto y corre:

docker service logs traefik --since 30m | egrep -i "app\.laruta11\.cl|acme|challenge|error" | tail -n 200


letsencrypt ya estaba escrito y guardado antes. lo borre guardé y volví a escribir y guarde-- ... eso generaría otro lesencrypt verdad?
Kodee
Sí, Ricardo Aníbal: al borrar y volver a habilitar/guardar en Easypanel, normalmente se genera una nueva solicitud/orden de Let’s Encrypt (y se emite un certificado nuevo si pasa el challenge), no “reutiliza” el anterior.

Ojo: Let’s Encrypt tiene límites de emisión; si haces muchos intentos seguidos puedes pegarte a rate limits. Si te da error, compárteme el mensaje y revisamos los logs de Traefik/ACME.


ok ya solicite otro letsencrypt que hago ahora.
Kodee
Ricardo Aníbal, ahora:

En Easypanel, revisa el estado del SSL para app.laruta11.cl (debería pasar a Active en 1–5 min).
Prueba desde la VPS:
curl -4I https://app.laruta11.cl
Si no activa o da error, necesito el mensaje exacto que te muestra Easypanel en la pantalla de SSL (texto tal cual).


ok ya solicite otro letsencrypt que hago ahora.
Kodee
Ricardo Aníbal:

En Easypanel → Domains → app.laruta11.cl, espera a que el SSL cambie a Active/Issued (suele tardar 1–5 min) y no reintentes mientras está procesando.
Verifica:
curl -4I https://app.laruta11.cl
Si falla o queda “pending”, pégame el error exacto que muestra Easypanel (texto tal cual) y el timestamp aproximado del intento.


y donde sale eso.. eso no lo veo "En Easypanel → Domains → app.laruta11.cl, espera a que el SSL cambie a Active/Issued (suele tardar 1–5 min) y no reintentes mientras está procesando.
"
Kodee
Ricardo Aníbal, en Easypanel lo ves en el mismo lugar donde agregaste el dominio:

Project (tu proyecto) → tu App/Service → Domains
En la fila de app.laruta11.cl debería aparecer algo como SSL / HTTPS / Let’s Encrypt con un estado (Pending/Issued/Active) o un botón Enable/Retry.
Si no te aparece ninguna columna/estado, dime qué versión/interfaz ves: ¿estás en App → Domains o en Project → Domains? Si puedes, sube una captura de esa pantalla.


asi
Attachment_698dccdad3a618.13368226.png
Kodee
Ricardo Aníbal, en tu captura el certificado aún es el self‑signed (por eso curl: (60)), o sea que Let’s Encrypt **todavía no se emitió/instaló.

En esa misma pantalla (Service → Domains), en la fila de app.laruta11.cl abre el menú de acciones (los 3 puntos) y busca Enable SSL / Issue Let’s Encrypt / Renew; ahí mismo debería mostrar Pending/Issued o un error.

Para confirmar desde la VPS qué certificado está sirviendo ahora:

curl -vk https://app.laruta11.cl/ 2>&1 | head -n 40
Pégame esas líneas y te digo exactamente qué está presentando Traefik y el siguiente paso.


eso que dices no existe lo del pending o issued...
Attachment_698dcd99ee99b8.15366461.png
Kodee
Ricardo Aníbal, correcto: en Easypanel no siempre muestra “pending/issued”. En tu modal, el SSL se controla con el switch HTTPS (arriba).

Haz esto:

Activa HTTPS → Save.
Espera 2–5 minutos.
Verifica desde la VPS:
curl -4I https://app.laruta11.cl
Si sigue saliendo self-signed o falla, pega el resultado de:

curl -vk https://app.laruta11.cl/ 2>&1 | head -n 60


.
Kodee
Ricardo Aníbal, ¿qué pasó al final?

Pega aquí el resultado de:

curl -4I https://app.laruta11.cl
y (si sigue con error de certificado):

curl -vk https://app.laruta11.cl/ 2>&1 | head -n 60


root@srv1331519:~# curl -vk https://app.laruta11.cl/ 2>&1 | head -n 40
% Total % Received % Xferd Average Speed Time Time Time Current
Dload Upload Total Spent Left Speed
0 0 0 0 0 0 0 0 --:--:-- --:--:-- --:--:-- 0* Host app.laruta11.cl:443 was resolved.

IPv6: (none)
IPv4: 76.13.126.63
Trying 76.13.126.63:443...
Connected to app.laruta11.cl (76.13.126.63) port 443
ALPN: curl offers h2,http/1.1
} [5 bytes data]
TLSv1.3 (OUT), TLS handshake, Client hello (1):
} [512 bytes data]
TLSv1.3 (IN), TLS handshake, Server hello (2):
{ [122 bytes data]
TLSv1.3 (IN), TLS handshake, Encrypted Extensions (8):
{ [15 bytes data]
TLSv1.3 (IN), TLS handshake, Certificate (11):
{ [815 bytes data]
TLSv1.3 (IN), TLS handshake, CERT verify (15):
{ [264 bytes data]
TLSv1.3 (IN), TLS handshake, Finished (20):
{ [36 bytes data]
TLSv1.3 (OUT), TLS change cipher, Change cipher spec (1):
} [1 byte
Kodee
Ricardo Aníbal, ese output todavía no muestra qué certificado está entregando (se corta antes del Subject/Issuer).

Ejecuta esto para ver si ya es Let’s Encrypt o sigue self‑signed:

curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:|SSL certificate problem|expire date|start date" | head -n 20
Pégame esas líneas. Si el issuer no dice Let’s Encrypt, entonces el switch HTTPS quedó en “TLS/self-signed” o Traefik aún no pudo completar el challenge y hay que revisar el motivo (DNS/puerto 80/443 o rutas).


TLSv1.3 (OUT), TLS change cipher, Change cipher spec (1):
} [1 bytes data]

TLSv1.3 (OUT), TLS handshake, Finished (20):
} [36 bytes data]
SSL connection using TLSv1.3 / TLS_AES_128_GCM_SHA256 / X25519 / RSASSA-PSS
ALPN: server accepted h2
Server certificate:
subject: CN=Easypanel
start date: Feb 4 10:53:41 2026 GMT
expire date: Feb 2 10:53:41 2036 GMT
issuer: CN=Easypanel
SSL certificate verify result: self-signed certificate (18), continuing anyway.
Certificate level 0: Public key type RSA (2048/112 Bits/secBits), signed using sha256WithRSAEncryption
{ [5 bytes data]
TLSv1.3 (IN), TLS handshake, Newsession Ticket (4):
{ [122 bytes data]
using HTTP/2
[HTTP/2] [1] OPENED stream for https://app.laruta11.cl/
[HTTP/2] [1] [:method: GET]
root@srv1331519:~#
Kodee
Ricardo Aníbal, sigue saliendo el certificado self‑signed de Easypanel (subject/issuer: CN=Easypanel), o sea Let’s Encrypt no se emitió para app.laruta11.cl.

Lo que toca verificar (en este orden):

DNS del subdominio apunta a tu VPS (76.13.126.63):
dig +short app.laruta11.cl A
Que puerto 80 esté accesible desde Internet (Let’s Encrypt lo necesita). Desde la VPS:
curl -4I http://app.laruta11.cl/.well-known/acme-challenge/test
Si 1 y 2 están OK, el bloqueo suele ser firewall: muestra reglas y puertos escuchando:
ufw status verbose
ss -lntp | grep -E ":80|:443"
Pégame las salidas de esos 4 comandos y te digo exactamente qué ajustar para que Easypanel emita el SSL real.


root@srv1331519:# dig +short app.laruta11.cl A
76.13.126.63
root@srv1331519:# root@srv1331519:# curl -4I http://app.laruta11.cl/.well-known/acme-challenge/test
HTTP/1.1 404 Not Found
Date: Thu, 12 Feb 2026 12:58:08 GMT
ss -lntp | grep -E ":80|:443"
Status: inactive
LISTEN 0 4096 0.0.0.0:80 0.0.0.0:* users:(("docker-proxy",pid=250381,fd=7))
LISTEN 0 4096 0.0.0.0:443 0.0.0.0:* users:(("docker-proxy",pid=250403,fd=7))
LISTEN 0 4096 [::]:80 [::]:* users:(("docker-proxy",pid=250386,fd=7))
LISTEN 0 4096 [::]:443 [::]:* users:(("docker-proxy",pid=250409,fd=7))
root@srv1331519:#
root@srv1331519:~#
Kodee
Ricardo Aníbal, DNS y HTTP por el puerto 80 están bien (ese 404 es normal), y no tienes UFW bloqueando. El motivo de que siga el certificado CN=Easypanel es que Traefik/Easypanel no está usando (o no pudo generar) el certificado Let’s Encrypt para ese host.

Haz esto en Easypanel:

En Service → Domains → app.laruta11.cl, cambia HTTPS a Let’s Encrypt (no “Self-signed/Default”) y Save.
Luego desactiva HTTPS, Save, vuelve a activar HTTPS (Let’s Encrypt) y Save (fuerza re-emisión).
Espera 2–5 minutos y prueba:
curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:" | head -n 5
Si el issuer sigue siendo Easypanel, necesito ver los logs de Traefik/Easypanel: en Easypanel abre Traefik / Proxy → Logs y pégame las líneas que contengan acme, challenge o error (del momento en que activas HTTPS).


lo que me dices es que desactive https y guarde.. luego lo active otra vez?
Kodee
Sí, Ricardo Aníbal:

En Update Domain pon HTTPS = OFF → Save.
Espera ~30 segundos.
Vuelve a abrir el dominio y pon HTTPS = ON (idealmente modo Let’s Encrypt si te da a elegir) → Save.
Espera 2–5 min y confirma:
curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:" | head -n 5


y usar ssltrust?
Kodee
No, Ricardo Aníbal: no uses ssltrust para esto; eso solo “confía” localmente en un certificado self‑signed y no arregla que el navegador/usuarios vean el TLS inválido.

Lo correcto es que Easypanel emita Let’s Encrypt (issuer debe decir Let’s Encrypt). Después de reactivar HTTPS, valida con:

curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:" | head -n 5


root@srv1331519:~# curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:" | head -n 5

subject: CN=Easypanel
issuer: CN=Easypanel
root@srv1331519:~#
Kodee
Ricardo Aníbal, Easypanel no está pudiendo emitir/usar Let’s Encrypt y por eso se queda en el cert self‑signed.

Dame los logs del proxy (ahí estará el motivo exacto):

En Easypanel abre Proxy / Traefik → Logs (o Settings → Proxy → Logs).
Desactiva y vuelve a activar HTTPS para app.laruta11.cl (para generar el evento).
Copia aquí las líneas del log que contengan:
acme
challenge
error
laruta11.cl
Si prefieres por terminal, dime el nombre del container de traefik/easypanel que te aparece en docker ps y te saco los logs exactos.


root@srv1331519:~# curl -vk https://app.laruta11.cl/ 2>&1 | grep -E "subject:|issuer:" | head -n 5

subject: CN=app.laruta11.cl
issuer: C=US; O=Let's Encrypt; CN=R12
root@srv1331519:~#
Kodee
Ricardo Aníbal, perfecto: ya está activo Let’s Encrypt (issuer: … Let's Encrypt R12) para app.laruta11.cl.

Ahora solo valida que responda bien en HTTPS:

curl -4I https://app.laruta11.cl
Si el navegador aún muestra el certificado viejo, prueba en incógnito o limpia caché SSL/DNS del equipo.


root@srv1331519:~# curl -4I https://app.laruta11.cl
HTTP/2 200
accept-ranges: bytes
content-type: text/html
date: Thu, 12 Feb 2026 13:06:52 GMT
etag: "2769-64a9f43804840"
last-modified: Thu, 12 Feb 2026 12:02:33 GMT
server: Apache/2.4.66 (Debian)
vary: Accept-Encoding
content-length: 10089
Kodee
Ricardo Aníbal, listo: HTTPS está funcionando correctamente y devuelve HTTP/2 200 con el certificado de Let’s Encrypt.

Si quieres, revisamos el otro dominio (agenterag.com o algún subdominio) para repetir el mismo proceso.


entonces cuales son los pasos correctos que hicimos el paso a paso infalible para replicar en mis otros sitios web?
Kodee
Ricardo Aníbal, paso a paso “infalible” para Easypanel + Let’s Encrypt (replicable):

DNS: crea/ajusta el registro A del dominio/subdominio (ej. app) apuntando a la IP de tu VPS.
Espera propagación y valida desde la VPS:
dig +short app.tudominio.com A
(debe devolver la IP de la VPS)

Verifica HTTP por 80 (Let’s Encrypt lo usa):
curl -4I http://app.tudominio.com/.well-known/acme-challenge/test
(con que responda algo tipo 404/200 está bien; lo importante es que conecte al host correcto)

En Easypanel → Service → Domains agrega el dominio/subdominio y deja HTTPS OFF, Save.
Forzar emisión: pon HTTPS ON (Let’s Encrypt), Save. Si no emite, haz el “reset” que te funcionó: OFF → Save → ON (Let’s Encrypt) → Save.
Confirma el certificado desde la VPS:
curl -vk https://app.tudominio.com/ 2>&1 | grep -E "subject:|issuer:" | head -n 5
(debe decir issuer: ... Let's Encrypt)

Confirma respuesta:
curl -4I https://app.tudominio.com
Nota rápida: asegúrate que 80 y 443 estén abiertos y que Traefik/Easypanel esté publicando esos puertos (como en tu ss -lntp).


