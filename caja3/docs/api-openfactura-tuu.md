Openfactura - API
Openfactura utiliza una API RESTful basada en HTTP. Las peticiones y respuestas de la API se hacen por medio del uso de mensajes formateados en JSON.
 ℹ️ Openfactura ofrece un entorno de desarrollo completamente operativo para que los desarrolladores e interesados puedan integrar sus aplicaciones y sistemas sin requerir una cuenta. Cabe destacar que las emisiones utilizan un CAF simulado, por lo que el timbre no puede ser validado. 

Comunidad
Disponemos de un canal en Slack en el cual pueden participar, ya sea para realizar consultas y/o comentarios que tengas sobre la integración.
 https://haulmer.slack.com 

Historial de cambios
 Fecha	Detalle Cambio
 06/08/2025	Se agrega nuevo campo ivaExceptional para hacer uso de IVA para artesanos.
 03/04/2025	Se elimina interfaz de ambiente demo. Integración es 100% via API.
 01/02/2025	Se puede usar emisión de enlaces para autoservicio por API.
 31/01/2025	Se incorporá campo sendEmail de enviar email en API emisión DTE
 27/12/2024	Dos nuevos campos para filtrar DTE: FchRecepOF y FchRecepSII.
Autorización y Autenticación
Todas las peticiones que se realizan a la API de Openfactura deben contener en la cabecera la credencial API Key, la cual es utilizada para verificar los permisos sobre el endpoint que intenta acceder, además de identificar quién es el que realiza la petición sobre el sistema.
Las API Keys generadas por el sistema están siempre asociadas a un usuario-empresa. Por lo que todas las consultas realizadas a través de la API con una API Key estarán ligadas a las acciones del "dueño" de la empresa.
Seguridad
Se debe tener en consideración los límites que ofrece la API para su implementación, actualmente se tiene un límite de peticiones por segundos y minutos que se pueden realizar.
límite de consultas por segundos: 3
límite de consultas por minutos: 100
Cuando se alcance alguno de estos límites el sistema reponderá con un código HTTP de error 429 y el mensaje:
json
{ "statusCode": 429, "message": "Rate limit is exceeded. Try again in X seconds." }
Solicitud API Key
Openfactura dispone de dos API Key públicas para facilitar y agilizar la integración con la API (la cual puedes encontrar más abajo en está misma documentación).
Cuando exista un error de validación de API Key el sistema reponderá con un código HTTP de error 401 y el mensaje:
 View More
json
{ "statusCode": 401, "message": "Access denied due to invalid subscription key. Make sure to provide a valid key for an active subscription." }
URLs
Existen dos URL para las APIs, una para cada ambiente:
Producción: https://api.haulmer.com
Desarrollo: https://dev-api.haulmer.com
Documentación SII
Para efectos de la API de emisión de OpenFactura, esta sigue la misma convención de nombre y jerarquía, solo van en formato compatible con JSON.
Formato de documentos electrónicos del SII.
Formato boletas electrónicas del SII.
APIs REST
POST
/anularDTE52
https://dev-api.haulmer.com/v2/dte/anularDTE52
API que permite anular Guía de Despacho Electrónica dte 52, además realiza la validación que no se pueda anular el documento más de una vez.
Authorization
 Campo	Requerido	Tipo
 apikey	*	string
Parámetros JSON
 Campo	Requerido	Tipo	Desc.
 dte	*	int	Tipo dte
 folio	*	int	Folio del documento
 Fecha	*	string	Fecha emisión del documento formato Y-m-d
Respuesta
 Campo	Requerido	Tipo
 succes	*	string
Respuesta de Error
 Nodo Padre	Campo	Req.	Tipo	Regla
 error	message	*	string	Descripción del error.
 error	code	*	string	Código asignado al error.
 error	details		arreglo(objetos)	Arreglo con los errores.
Plain Text
{ 
  "error": {
    "message": string,
    "code": "OF-10",
    "details": array
  }
}
 Nodo Padre	Campo	Tipo	Regla
 details	field	string	Campo del error.
 details	issue	string	Detalle del error.
Plain Text
{ 
  "details": [
    { "field": string, "issue": string },
    { "field": string, "issue": string }
    ]
}
HEADERS
apikey
928e15a2d14d4a6292345f04960f4bd3
Content-Type
application/json
Body
raw (json)
json
{"Dte": 52,"Folio": 34972,"Fecha": "2020-10-16"}
Example Request
Anula-dte52

curl
curl --location 'https://dev-api.haulmer.com/v2/dte/anularDTE52' \
--header 'apikey: 928e15a2d14d4a6292345f04960f4bd3' \
--header 'Content-Type: application/json' \
--data '{"Dte": 52,"Folio": 34972,"Fecha": "2020-10-16"}'
200 OK
Example Response
Body
Headers (11)
json
{
  "success": "Se ha anulado el documento Folio: 34972"
}
GET
Buenas Prácticas Producción
Idempotency Key
Utilizar siempre que sea posible la idempotencia para las emisiones de DTE, de esta forma se puede tener la seguridad que al reintentar no se producirá una doble emisión en el sistema.
Dependiendo de la integración la Idempotency Key va a variar, dado que el cliente es responsable de asegurar su unicidad, los usos más comunes corresponden a; el número de la orden de compra, número aleatorio generado en la sesión del cliente, entre muchos otros.
Generación PDF
Con el fin de reducir los tiempos de respuesta durante una emisión, hay que tener en consideración que la generación del PDF puede llegar a representar hasta el 60% del tiempo de una emisión. Dependiendo del tipo de integración pueden haber casos que no es necesario contar con el PDF en ese mismo instante que se está haciendo la emisión del DTE, ya sea porque cuentan con su propia representación de 57mm, 80mm o PDF. Para esos casos de uso se recomienda no solicitar el PDF en la respuesta (objeto response de la API Emisión), reduciendo de esta forma drásticamente los tiempos de respuesta. Para la tranquilidad del integrador, si no se solicita el PDF en la emisión, cuando el PDF sea requerido ya sea vía API o interfaz de Openfactura, se generará en el momento con la configuración que se haya mandado en el objeto response (80MM o LETTER).
Example Request
Buenas Prácticas Producción

curl
curl --location ''
Example Response
Body
Headers (0)
No response body
This request doesn't return any response body
GET
Preguntas Frecuentes
1.- ¿Dónde solicito mi API Key de producción?
Se puede generar API Key desde la Plataforma de Openfactura , Tutorial de apikey.
2.- ¿Qué debo hacer para pasar a producción?
Además de contar con la API Key de la empresa que se desea integrar, se debe tener especial cuidado en cambiar la URL de conexión con Openfactura por la de producción: https://api.haulmer.com
3.- ¿Cómo sé si el DTE fue emitido correctamente?
Si luego de la emisión recibes el token del documento esto quiere decir que fue recepcionado correctamente por el sistema Openfactura. Para confirmar que el documento ya fue entregado al S.I.I. puedes consultar la API status del documento, cabe destacar que el tiempo de entrega del documento al S.I.I. varía dependiendo la cantidad de emisiones realizadas, en la mayoria de los casos no debe tomar más de 3 minutos.
4.- ¿Dónde subo o gestiono los CAF?
Openfactura se encarga de gestionar los CAF automáticamente para todo los clientes, por lo tanto ya no se deben subir los CAF de forma manual en el sistema.
5.- ¿Qué puedo hacer si no tengo folios para emitir?
Ya que la gestión de CAF se hace de forma automática, cuando el sistema no es capaz de generar un nuevo CAF se debe principalmente porque el contribuyente tiene situaciones pendientes con el S.I.I., en la medida que sea posible se notificará al Contribuyente con problemas antes de que el CAF sea consumido en su totalidad para evitar de esta forma que el Contribuyente quede inoperable.