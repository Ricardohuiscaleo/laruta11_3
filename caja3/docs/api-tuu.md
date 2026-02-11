
Integración de Pasarela de Pago TUU.cl: Un Caso de Uso con Implementaciones para PHP, Astro y PWA - Un Informe Técnico y Arquitectónico Completo


1. Resumen Ejecutivo: La Estrategia ante la Incertidumbre Documental

Este informe técnico presenta una guía exhaustiva para la integración de una pasarela de pago, utilizando la API de TUU.cl como caso de estudio. La solución propuesta se fundamenta en una arquitectura de tres capas: una capa de backend segura y robusta implementada en PHP, una capa de frontend moderna y de alto rendimiento construida con el framework Astro, y una capa de resiliencia y experiencia de usuario a través de una Progressive Web App (PWA).
La investigación de la documentación oficial de TUU.cl para desarrolladores revela un punto crítico que define la estrategia de este proyecto. Se ha verificado que, si bien la documentación está organizada de manera clara, con secciones dedicadas al inicio, pago presencial, servicios adicionales y partners, las secciones específicas para "TUU Pago Online" y "APIs de referencia" se encuentran marcadas con la indicación "próximamente".1 Adicionalmente, el recurso para la "Recepción del callback" es inaccesible.4 Este hallazgo es de suma importancia, ya que la integración de una pasarela de pago en línea depende enteramente de la capacidad de inicializar transacciones y, fundamentalmente, de recibir notificaciones asincrónicas sobre el estado final de las mismas.
Ante esta falta de información específica, este documento trasciende el propósito de ser un simple manual de implementación. En cambio, se ha concebido como un plan arquitectónico completo, diseñado para ser seguro, escalable y, sobre todo, adaptable. La solución se basa en las mejores prácticas de la industria de pagos digitales, utilizando la documentación de pasarelas de pago maduras, como la de Mercado Pago 5, como un modelo de referencia para la lógica de los webhooks, la seguridad y la fiabilidad. Esta metodología asegura que la implementación sea sólida desde el principio y facilite una transición fluida una vez que la documentación completa de TUU.cl esté disponible.
Las recomendaciones clave de este informe se centran en la priorización de la seguridad, la gestión de flujos asincrónicos mediante un listener de webhooks, el uso de variables de entorno para la API Key, y la implementación de un enfoque modular que separe las responsabilidades de cada componente. Esto no solo mitiga los riesgos inherentes a la incertidumbre documental, sino que también establece una base técnica de alta calidad para el proyecto.

2. Fundamentos Arquitectónicos de una Transacción de Pago Digital

La integración de una pasarela de pago no es un proceso lineal de una única petición y respuesta. Es un flujo de eventos complejo y, en su mayor parte, asincrónico. Una arquitectura robusta debe considerar cada etapa del ciclo de vida de la transacción, desde la iniciación hasta la notificación final y la conciliación.

2.1. El Rol de cada Componente en la Arquitectura

La arquitectura propuesta se basa en una separación clara de responsabilidades entre tres componentes principales, cada uno con un rol específico y crítico para el éxito de la integración.
Backend (PHP): La Capa Segura y Autorizativa: El backend en PHP actúa como el único punto de contacto entre el sistema del comerciante y la API de TUU.cl. Su función principal es proteger la información sensible, como la API Key 6, y orquestar el flujo de la transacción. El backend recibe las peticiones de inicialización de pago desde el frontend, se comunica con la API de TUU.cl y, de manera crucial, procesa las notificaciones asincrónicas de los webhooks. La lógica de negocio crítica, como la actualización de la base de datos de pedidos, reside exclusivamente en esta capa, garantizando que el estado de la transacción no pueda ser alterado por una fuente externa no autorizada.
Frontend (Astro): La Capa de Presentación y Experiencia de Usuario: El frontend, construido con Astro, es responsable de la interacción con el usuario. Su tarea es capturar la información del pedido y los datos del comprador, para luego iniciar el proceso de pago a través de una API REST que expone el backend en PHP. Es fundamental que el frontend nunca maneje la API Key ni se comunique directamente con la pasarela de pago. Astro, con su enfoque en el rendimiento y la pre-renderización, asegura que la interfaz de pago sea rápida y reactiva, mejorando la experiencia general del usuario.
PWA (Service Worker): La Capa de Resiliencia y Notificaciones: La implementación de una Progressive Web App (PWA) eleva la experiencia del usuario más allá de una simple página web. A través de un Service Worker, la aplicación puede ofrecer una funcionalidad mejorada, como el acceso sin conexión a ciertos activos y, lo que es más importante, la recepción de notificaciones push. Esto permite que el sistema notifique al usuario sobre el estado de su pago en tiempo real, incluso si ha cerrado la página, cerrando así el ciclo de comunicación asincrónica de manera efectiva.

2.2. Flujo de Datos y Eventos en la Transacción

Comprender el flujo de una transacción es fundamental para una integración exitosa. No es suficiente con que el cliente inicie el pago. El sistema debe saber el resultado final de la transacción, que a menudo ocurre fuera del control del frontend. El siguiente flujo detallado, presentado en la Tabla 1, ilustra la cadena de eventos y la interdependencia entre los componentes.
Tabla 1: Flujo Detallado de una Transacción de Pago
Paso
Responsable
Descripción de la Acción
Información Clave Intercambiada
1.
Usuario
Inicia la compra y hace clic en "Pagar".
$amount, $orderId, $productId
2.
Frontend (Astro)
Llama a la API del backend para iniciar el proceso de pago.
Petición POST a /api/checkout con los datos del pedido.
3.
Backend (PHP)
Valida los datos del pedido y llama a la API de TUU.cl.
Petición POST con la API Key, $amount, $orderId, y URLs de redirección (success_url, failure_url).
4.
TUU.cl API
Procesa la solicitud y retorna una URL de pago.
HTTP 200 OK con un JSON que contiene $paymentUrl y $transactionId.
5.
Backend (PHP)
Almacena el $transactionId en la base de datos del pedido y lo envía al frontend.
JSON con $paymentUrl y $transactionId.
6.
Frontend (Astro)
Redirige al usuario a la $paymentUrl para completar la transacción.
Redirección del navegador a la página de pago segura de TUU.cl.
7.
Usuario
Completa el pago en el sitio de TUU.cl.
Ingresa la información de su tarjeta y autoriza la transacción.
8.
TUU.cl (Webhook)
Envía una notificación asincrónica al backend del comerciante.
Petición POST a la notification_url del webhook con el estado final del pago (aprobado, rechazado, etc.) y una firma de seguridad (x-signature).
9.
Backend (PHP)
Recibe la notificación, valida su origen y actualiza la base de datos.
Responde con HTTP STATUS 200 o 201 para confirmar la recepción. Lógica de actualización del pedido en la BD.
10.
Backend (PHP)
(Opcional) Envía una notificación push a la PWA.
Mensaje JSON con el estado final del pago.
11.
PWA (Service Worker)
Muestra una notificación push al usuario final.
Notificación emergente en el dispositivo del usuario.

Como se puede observar en la tabla, el estado final del pago (éxito o fracaso) no se conoce en el momento de la redirección. La transacción se completa en un entorno externo (TUU.cl), lo que genera una desconexión. La única forma de sincronizar el estado del pedido en el sistema del comerciante es a través de un mecanismo de comunicación de servidor a servidor, como un webhook. Sin un webhook, el sistema permanecería ciego al resultado real del pago, lo que podría llevar a errores de inventario, problemas de servicio al cliente y pérdida de ingresos.

2.3. El Papel Crítico de los Webhooks (Callbacks)

Un webhook es un método para que una aplicación externa envíe información a otra aplicación de forma automática y en tiempo real cuando ocurre un evento específico.7 En el contexto de las pasarelas de pago, el evento es la finalización de una transacción. El servicio de pagos (TUU.cl) notifica al servidor del comerciante sobre el resultado, eliminando la necesidad de que el comerciante sondee repetidamente a la API de pagos para verificar el estado de la transacción.
No obstante, la implementación de un webhook exige una rigurosa atención a la seguridad y la fiabilidad. Un receptor de webhook que simplemente procesa cualquier petición entrante sin validación es vulnerable a ataques de falsificación de peticiones (CSRF) o inyección de datos maliciosos. Para mitigar estos riesgos, las pasarelas de pago maduras, como se describe en la documentación de Mercado Pago, envían una firma de seguridad en el encabezado de la petición.5 Esta firma, a menudo llamada
x-signature o similar, es una firma HMAC (Código de Autenticación de Mensajes con Hash) que se genera usando la API Key secreta del comerciante y el cuerpo de la petición. El receptor del webhook debe recalcular esta firma y compararla con la que se recibió en el encabezado. Si las firmas no coinciden, la petición debe ser rechazada.5 Adicionalmente, se utiliza un
timestamp para prevenir los ataques de repetición.
Otro aspecto fundamental es la idempotencia. La pasarela de pago podría reintentar el envío de una notificación si no recibe una respuesta HTTP STATUS 200 o 201 en un tiempo determinado.5 Esto significa que el servidor del comerciante podría recibir la misma notificación varias veces. La lógica de procesamiento del webhook debe ser capaz de manejar esta situación sin causar efectos secundarios, como duplicar un pago o un pedido. Esto se logra típicamente verificando en la base de datos si la transacción con el
$transactionId recibido ya ha sido procesada. Si es así, se descarta el evento duplicado.

3. Implementación del Backend con PHP: El Corazón de la Pasarela de Pago

El backend en PHP es el componente más crítico de la arquitectura, ya que es el único que interactúa directamente con la pasarela de pago y gestiona la lógica de negocio sensible.

3.1. Configuración del Entorno y Gestión de la API Key

La API Key de TUU.cl es la credencial principal para autenticar las peticiones.6 Su seguridad es primordial. En un entorno de producción, la API Key nunca debe codificarse directamente en el código fuente. La práctica recomendada es utilizar variables de entorno.
El proceso de obtención de la API Key, según la documentación, es sencillo:
Navegar a la sección "Pagos" en el menú principal.
Seleccionar "Configuración" en el menú lateral.
Elegir "API" y hacer clic en "Generar api key".6
Para gestionar esta clave de forma segura en PHP, se puede utilizar un archivo .env o la configuración del servidor web (por ejemplo, Apache o Nginx). Un archivo .env es una solución común en entornos de desarrollo y staging:

Fragmento de código


TUU_API_KEY="sk_live_XXXXXXXXXXXXXXXXXXXXXX"


El script de PHP cargaría esta variable de entorno al inicio.

3.2. Diseño de la Clase de Servicio TUU.cl

Se propone una clase TUUClient que encapsule la lógica de comunicación con la API de TUU.cl. Dado que los puntos de referencia para "TUU Pago Online" no están documentados, los métodos de esta clase serán una simulación basada en la nomenclatura y el comportamiento estándar de las pasarelas de pago. Se utilizará un cliente HTTP como Guzzle para las peticiones.

PHP


<?php

// Ejemplo simulado de la clase cliente para la API de TUU.cl
// La implementación real dependerá de la documentación oficial.

require 'vendor/autoload.php';

use GuzzleHttp\Client;

class TUUClient {
    private Client $httpClient;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.tuu.cl/v1') {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->httpClient = new Client(,
            'verify' => true // En producción, siempre verificar el certificado SSL
        ]);
    }

    /**
     * Simula la inicialización de un pago en línea con la API de TUU.cl.
     * @param float $amount
     * @param string $orderId
     * @param string $description
     * @param string $successUrl URL de redirección en caso de éxito.
     * @param string $failureUrl URL de redirección en caso de fallo.
     * @param string $notificationUrl URL del webhook para notificaciones asincrónicas.
     * @return array La respuesta de la API que incluye una URL de pago.
     * @throws Exception Si la llamada a la API falla.
     */
    public function initializePayment(float $amount, string $orderId, string $description, string $successUrl, string $failureUrl, string $notificationUrl): array {
        try {
            // Este es un ejemplo de la carga de la petición, basándose en estándares comunes.
            $response = $this->httpClient->post('payments/initialize', [
                'json' => [
                    'amount' => $amount,
                    'order_id' => $orderId,
                    'description' => $description,
                    'redirect_urls' => [
                        'success' => $successUrl,
                        'failure' => $failureUrl
                    ],
                    'notification_url' => $notificationUrl
                ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Se asume que la respuesta contiene una URL a la que redirigir al usuario.
            if (!isset($responseData['payment_url']) ||!isset($responseData['transaction_id'])) {
                throw new Exception('Respuesta inesperada de la API de TUU.cl.');
            }

            return $responseData;
        } catch (GuzzleHttp\Exception\RequestException $e) {
            // Manejo de errores de la petición HTTP
            throw new Exception('Error al inicializar el pago: '. $e->getMessage());
        }
    }

    // Otros métodos de servicio como 'queryPaymentStatus', 'refundPayment', etc.
    // se añadirían aquí una vez que se conozcan los detalles de la API.
}


Este código demuestra cómo la clase TUUClient gestiona la autenticación con la API Key y prepara la petición POST. El enfoque modular facilita la adaptación futura a la documentación real de TUU.cl, ya que solo se necesitaría modificar los detalles de los endpoints y los parámetros.

3.3. Implementación del Webhook Listener de PHP (El Cerebro Asincrónico)

La implementación del webhook-listener.php es la pieza central de la solución. Este script no solo recibe la notificación, sino que también la valida para garantizar su autenticidad y procesa el evento de manera segura.
El siguiente ejemplo simula un listener de webhook que incorpora las medidas de seguridad descritas en la documentación de Mercado Pago 5, incluyendo la validación de la firma y la idempotencia.

PHP


<?php

// Archivo: webhook-listener.php
// Propósito: Recepción, validación y procesamiento seguro de callbacks de TUU.cl (simulado).

// Cargar variables de entorno de forma segura
// Asegurarse de que esta API Key es la misma que la utilizada en el TUUClient.php
$tuuApiKey = getenv('TUU_API_KEY'); 

// 1. Recepción de la petición POST y validación del método
if ($_SERVER!== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

$requestBody = file_get_contents('php://input');
$requestHeaders = getallheaders();

// 2. Validación de la firma de seguridad (HMAC)
// Se asume que TUU.cl utiliza un header similar a 'x-signature' y un 'timestamp'
// como lo hace Mercado Pago.
// La clave secreta para la firma es la misma API Key.
$receivedSignature = $requestHeaders['x-signature']?? '';
$receivedTimestamp = $requestHeaders['timestamp']?? '';

if (empty($receivedSignature) |

| empty($receivedTimestamp)) {
    http_response_code(400); // Bad Request
    error_log('Webhook recibido sin firma o timestamp.');
    exit;
}

// Calcular la firma esperada
$payloadToSign = $receivedTimestamp. '.'. $requestBody;
$expectedSignature = hash_hmac('sha256', $payloadToSign, $tuuApiKey);

// Comparación de firmas usando una comparación de tiempo constante para evitar ataques de temporización
if (!hash_equals($receivedSignature, $expectedSignature)) {
    http_response_code(403); // Forbidden
    error_log('Firma de webhook inválida. Posible ataque de falsificación.');
    exit;
}

// 3. Procesamiento del cuerpo del evento
$data = json_decode($requestBody, true);

// Se asume que el cuerpo contiene un ID de transacción y el estado.
$transactionId = $data['transaction_id']?? null;
$status = $data['status']?? null;

if (empty($transactionId) |

| empty($status)) {
    http_response_code(400);
    error_log('Cuerpo del webhook inválido o incompleto.');
    exit;
}

// 4. Implementación de la lógica de negocio y la idempotencia
// Lógica simulada de conexión a la base de datos (BD).
// Reemplazar con la lógica real de su sistema de gestión de pedidos.
try {
    // Iniciar una transacción de BD si el sistema lo soporta.

    // 4a. Cargar el pedido de la BD basado en el transactionId
    $order = fetchOrderFromDatabase($transactionId);

    if ($order === null) {
        // La transacción no existe en nuestra base de datos.
        // Esto podría ser un error o una notificación de un evento no esperado.
        // Loggear el error, pero responder 200 para no causar reintentos.
        error_log('Webhook para un transactionId no registrado: '. $transactionId);
        http_response_code(200);
        exit;
    }

    // 4b. Verificar el estado actual del pedido para asegurar la idempotencia.
    if ($order['status'] === 'completed' |

| $order['status'] === 'failed') {
        // El pedido ya ha sido procesado. Responder 200 y salir para evitar reintentos duplicados.
        error_log('Webhook recibido para un pedido ya procesado: '. $transactionId);
        http_response_code(200);
        exit;
    }

    // 4c. Actualizar el estado del pedido basado en el webhook.
    $newStatus = '';
    if ($status === 'approved') {
        $newStatus = 'completed';
    } elseif ($status === 'rejected') {
        $newStatus = 'failed';
    } elseif ($status === 'pending') {
        $newStatus = 'pending_review';
    }

    if (!empty($newStatus)) {
        updateOrderStatusInDatabase($transactionId, $newStatus);
    }

    // 5. Enviar la respuesta esperada de confirmación
    // Esto es crucial. La pasarela de pago lo necesita para no reintentar la notificación.
    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    // Si ocurre un error al procesar el webhook, se debe registrar y responder con un error
    // para que la pasarela de pago pueda reintentar la notificación más tarde.
    error_log('Error al procesar el webhook: '. $e->getMessage());
    http_response_code(500); // Internal Server Error
    exit;
}


La lógica de validación de la firma HMAC es un pilar de la seguridad en este sistema. Al usar la misma clave secreta (la API Key) que el servicio de pagos, el sistema del comerciante puede autenticar de forma criptográfica que el mensaje proviene de una fuente legítima. La inclusión de un timestamp evita que un atacante intercepte y reenvíe una petición de webhook legítima en el futuro. Finalmente, la lógica de idempotencia, que verifica el estado actual del pedido antes de cualquier actualización, protege contra los efectos secundarios negativos de las notificaciones duplicadas, garantizando la integridad de los datos.

4. Implementación del Frontend con Astro y PWA: La Experiencia de Usuario

La capa de frontend, desarrollada con Astro, se enfoca en la creación de una experiencia de usuario fluida y segura. Su principal responsabilidad es la presentación de la interfaz de pago y la orquestación del inicio del proceso de pago a través del backend.

4.1. Integración con Astro: La Interfaz de Pago

El componente de checkout en Astro (Checkout.astro) es un ejemplo de cómo una interfaz de usuario puede interactuar de manera segura con el backend. A diferencia de las arquitecturas monolíticas, Astro permite una clara separación entre el código del servidor y el del cliente, lo que fortalece la seguridad.

Fragmento de código


---
// Este es un componente de Astro para el formulario de checkout.
// La lógica del servidor y cliente están claramente separadas.

import { API_BASE_URL } from '../constants'; // Importar la URL del backend desde un archivo de configuración.

const orderDetails = {
    amount: 99.99,
    orderId: 'ORD-' + Math.random().toString(36).substring(2, 9),
    description: 'Compra de productos digitales'
};

---

<form id="payment-form">
    <h1>Finalizar Compra</h1>
    <p>Monto: ${orderDetails.amount}</p>
    <p>Pedido: {orderDetails.orderId}</p>
    <button type="submit">Pagar con TUU.cl</button>
</form>

<script is:inline>
    // Este script se ejecuta en el navegador del cliente.
    // La lógica de la llamada a la API y la redirección se maneja aquí.
    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Se asume que el backend expone un endpoint para iniciar el pago.
        // Se envía la información del pedido de manera segura.
        const response = await fetch('/api/checkout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                amount: {orderDetails.amount},
                orderId: '{orderDetails.orderId}',
                description: '{orderDetails.description}'
            }),
        });

        const data = await response.json();

        if (response.ok && data.payment_url) {
            // Si la respuesta es exitosa y contiene una URL de pago, redirigir al usuario.
            window.location.href = data.payment_url;
        } else {
            // Manejar errores de la API.
            console.error('Error al iniciar el pago:', data.error);
            alert('Hubo un error al procesar su pago. Por favor, intente de nuevo.');
        }
    });
</script>


El código muestra cómo la interfaz de usuario se limita a iniciar una petición POST a un endpoint del backend (ej. /api/checkout). Es el backend en PHP el que maneja la lógica de negocio y la comunicación con la API de TUU.cl, manteniendo la API Key y otros secretos lejos del alcance del navegador.

4.2. Consideraciones de una PWA

La capa PWA mejora la experiencia del usuario y complementa el flujo de pagos asincrónicos. Un Service Worker es un script que se ejecuta en segundo plano, independiente de la página web.
Cacheo de Activos: El Service Worker puede almacenar en caché los activos estáticos del sitio (HTML, CSS, JS, imágenes) para que la aplicación cargue casi instantáneamente en visitas posteriores y funcione sin conexión. Esto es crucial para una experiencia de usuario robusta.
Notificaciones Push: El Service Worker permite al backend enviar notificaciones push directamente al dispositivo del usuario. Esta es una característica poderosa que cierra el ciclo de comunicación. Una vez que el webhook-listener.php recibe y procesa la notificación de pago exitosa, el backend puede enviar un mensaje push al cliente, informándole que su pago ha sido aprobado. Este mecanismo es superior a la simple redirección, ya que el usuario recibe la confirmación instantánea, incluso si ha cerrado la pestaña del navegador o si la conexión se ha interrumpido después de la redirección.

JavaScript


// Archivo: sw.js
// Lógica del Service Worker para cacheo y notificaciones push.

const CACHE_NAME = 'payment-gateway-cache-v1';
const urlsToCache = [
    '/',
    '/checkout.astro',
    '/styles/main.css',
    '/images/logo.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
           .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
           .then((response) => response |

| fetch(event.request))
    );
});

// Lógica para recibir notificaciones push
self.addEventListener('push', (event) => {
    let payload = event.data.json();
    const options = {
        body: payload.body,
        icon: '/images/tuu-icon.png',
        badge: '/images/badge.png'
    };
    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});


Este código de Service Worker demuestra cómo se pueden manejar las notificaciones. La clave para que esto funcione es que el backend en PHP debe tener la lógica para enviar estas notificaciones push después de procesar el webhook, utilizando un servicio de mensajería push como VAPID o Firebase Cloud Messaging. Este enfoque avanzado no solo confirma el estado del pago, sino que también crea una conexión directa y personalizada con el cliente final.

5. Consideraciones de Seguridad y Resiliencia en Producción

La fase de despliegue a producción requiere un escrutinio minucioso de la seguridad y la fiabilidad. La implementación técnica debe ir acompañada de una serie de buenas prácticas que garanticen la integridad del sistema.

5.1. Protección de Datos Sensibles

Como se mencionó anteriormente, la API Key, las credenciales de la base de datos y cualquier otra información confidencial deben gestionarse mediante variables de entorno y no codificarse en el código fuente. Esto previene fugas de información en repositorios públicos y facilita la gestión de diferentes entornos (desarrollo, staging, producción) con configuraciones distintas.

5.2. Validación de Webhooks y Criptografía

La validación de la firma HMAC (x-signature) y el timestamp es una medida de seguridad no negociable. Un atacante podría intentar falsificar una notificación de pago exitoso para obtener un servicio o producto de manera fraudulenta. La verificación de la firma, que utiliza la API Key como clave secreta, es la única forma de garantizar que el origen del evento sea realmente TUU.cl. Sin esta validación, el sistema es extremadamente vulnerable.

5.3. Manejo de Errores y Reintentos

La robustez de la solución se mide por su capacidad para manejar fallos. En el backend PHP, es crucial implementar un manejo de errores adecuado con bloques try-catch para capturar excepciones en la comunicación con la API de TUU.cl o con la base de datos. Los logs detallados son fundamentales para el diagnóstico de problemas. En caso de que el webhook no pueda procesar una notificación (por ejemplo, debido a una interrupción de la base de datos), el sistema debe responder con un error (HTTP STATUS 500) para que la pasarela de pago reintente el envío de la notificación más tarde, siguiendo una política de reintentos con retroceso exponencial.

5.4. Un Análisis Crítico de las Limitaciones de la Documentación Actual de TUU.cl

La falta de una documentación clara y completa para la API de "TUU Pago Online" representa el mayor riesgo para la planificación del proyecto.1 La arquitectura propuesta en este informe se basa en suposiciones fundamentadas en las mejores prácticas de la industria, pero los detalles específicos de los endpoints, los nombres de los campos y el formato de las respuestas de la API de TUU.cl son desconocidos.
Este escenario exige una comunicación proactiva. El equipo de desarrollo debe establecer un canal de comunicación directo con el soporte de TUU.cl para solicitar la documentación completa tan pronto como sea posible. Se recomienda encarecidamente que no se avance en un proyecto de producción sin estos detalles críticos. La arquitectura presentada aquí está diseñada para ser flexible y facilitar la migración, pero la implementación final requerirá la información precisa.

6. Conclusiones y Recomendaciones Finales

La integración de una pasarela de pago digital es una tarea de alta responsabilidad que exige una arquitectura bien definida y un enfoque riguroso en la seguridad y la fiabilidad. Este informe ha presentado un plan completo para la integración de la API de TUU.cl, a pesar de la ausencia de documentación detallada para su servicio de pagos en línea. La solución propuesta se apoya en un backend PHP seguro, un frontend Astro de alto rendimiento y una capa de PWA para mejorar la experiencia del usuario, todos ellos interconectados de forma segura a través de un flujo asincrónico basado en webhooks.
La estrategia de utilizar un modelo de referencia maduro (Mercado Pago) ha permitido diseñar un sistema que es inherentemente robusto y seguro, con medidas de validación de la firma (x-signature) e idempotencia. Este enfoque no solo mitiga los riesgos de la incertidumbre documental, sino que también establece un estándar de calidad para el desarrollo.
A continuación, se presenta un checklist para garantizar un despliegue exitoso en un entorno de producción, basado en las consideraciones de este informe.
Tabla 2: Checklist de Despliegue para Producción
Eje
Tarea de Despliegue
Estado
Seguridad
API Key gestionada como variable de entorno
Incompleto


Claves secretas de webhooks configuradas y protegidas
Incompleto


Validación de firma HMAC en el listener de webhooks implementada
Incompleto


Certificado SSL/TLS válido instalado en el servidor
Incompleto


Acceso al listener de webhooks restringido por IP si es posible
Incompleto
Resiliencia
Lógica de idempotencia implementada en el procesador de webhooks
Incompleto


Sistema de logging centralizado para errores y eventos
Incompleto


Mecanismos de monitoreo y alerta configurados
Incompleto


Estrategia de reintentos con retroceso exponencial en llamadas a API
Incompleto
Infraestructura
Servidor web (Apache/Nginx) optimizado para carga
Incompleto


Base de datos con respaldo y optimizada para transacciones
Incompleto


Entorno de producción aislado y seguro
Incompleto
Funcionalidad
Pruebas de integración con el entorno de sandbox de TUU.cl
Incompleto


Pruebas de estrés y rendimiento realizadas
Incompleto


Notificaciones push de la PWA funcionando correctamente
Incompleto

Los próximos pasos para el equipo de desarrollo deben centrarse en la comunicación directa con el soporte de TUU.cl para obtener la documentación completa. Una vez que esta información esté disponible, la arquitectura propuesta facilitará una transición rápida y segura, permitiendo el reemplazo de los métodos simulados con la implementación real de la API. Se recomienda encarecidamente que se realicen pruebas exhaustivas en un entorno de sandbox antes de cualquier lanzamiento en vivo para garantizar que todas las piezas del sistema funcionen en armonía.
Obras citadas
TUU - Página de integraciones, fecha de acceso: agosto 26, 2025, https://developers.tuu.cl/docs/getting-started
TUU - Página de integraciones, fecha de acceso: agosto 26, 2025, https://developers.tuu.cl/
TUU Demo, fecha de acceso: agosto 26, 2025, https://developers.tuu.cl/docs/tuu-demo
fecha de acceso: diciembre 31, 1969, https://developers.tuu.cl/docs/recepcion-del-callback
Webhooks - Notificaciones - Mercado Pago Developers, fecha de acceso: agosto 26, 2025, https://www.mercadopago.cl/developers/es/docs/checkout-pro/additional-content/notifications/webhooks
Generación de API Key en espacio de trabajo, fecha de acceso: agosto 26, 2025, https://developers.tuu.cl/docs/generaci%C3%B3n-de-api-key-en-espacio-de-trabajo
¿Qué es una notificación webhook? - InvestGlass, fecha de acceso: agosto 26, 2025, https://www.investglass.com/es/what-is-a-webhook-notification/

respuestas:

Análisis Técnico y Guía de Integración para la API de Pagos de TUU.cl
1. Resumen Ejecutivo
El presente informe ofrece un análisis exhaustivo de la documentación disponible para la API de pagos de TUU.cl, con un enfoque en la integración de servicios de pago remoto. La investigación revela una arquitectura de API funcional y coherente para la creación y consulta de pagos, alojada en el dominio https://integrations.payment.haulmer.com. El sistema de autenticación se basa en una clave API (   

X-API-Key), que se gestiona de manera segura a través del espacio de trabajo del comercio. Un aspecto notable es el soporte para la idempotencia, lo que es una buena práctica para prevenir transacciones duplicadas.   

No obstante, el análisis identifica lagunas documentales críticas que presentan riesgos significativos para una implementación en producción. La documentación clave sobre la "Recepción del callback" está inaccesible , y no se encontraron detalles sobre los mecanismos de    

webhooks o los parámetros de redirección para comunicar el resultado de una transacción al sistema del integrador. Esta ausencia de información vital sugiere que las confirmaciones de pago deben ser gestionadas a través de un mecanismo de polling, lo cual introduce ineficiencias operativas. Además, las secciones que describen las APIs para "Pago Online," "Facturación Electrónica" y "Firma Electrónica" están marcadas como "próximamente," lo que limita la capacidad actual de la plataforma para integraciones puramente basadas en la web sin un dispositivo físico. En general, la ausencia de recursos estándar de la industria, como un    

Postman collection o un repositorio oficial de GitHub, indica que la plataforma de desarrolladores de TUU.cl se encuentra en una fase de maduración inicial.   

2. Introducción: Visión General y Alcance del Análisis
TUU.cl se posiciona como un proveedor de soluciones de punto de venta (POS) y servicios de facturación electrónica, permitiendo a los comercios aceptar pagos con diversas tarjetas y emitir documentos tributarios. La plataforma de desarrolladores de TUU.cl tiene como objetivo facilitar la integración de sistemas externos con estos servicios. La documentación para desarrolladores está estructurada en secciones que cubren el inicio, pago presencial, servicios adicionales y un área para integradores de aplicaciones.   

El propósito de este informe es proporcionar un análisis técnico y crítico de la documentación disponible para la API de pagos, atendiendo a los puntos clave de la solicitud inicial: la URL base, los endpoints para la creación de pagos, el método de autenticación, la estructura de la respuesta, el manejo de webhooks/callbacks y los parámetros de redirección. La evaluación se ha realizado basándose exclusivamente en los materiales de investigación proporcionados, lo cual permite identificar tanto la información explícita como las deficiencias documentales para informar una estrategia de integración robusta y con mitigación de riesgos.

3. Arquitectura y Endpoints de la API de Pagos
3.1 Análisis del Endpoint de Creación de Pagos

El punto de entrada principal para iniciar una transacción es a través de un endpoint de creación de pagos. La URL base para las integraciones de la API se encuentra en https://integrations.payment.haulmer.com/. La API utiliza un subdominio de "Haulmer," lo que sugiere una asociación técnica o que TUU.cl utiliza la infraestructura de un tercero para sus servicios de pago. El    

endpoint específico para iniciar un pago remoto es POST https://integrations.payment.haulmer.com/PaymentRequest/Create.   

Este endpoint está diseñado para un flujo de "Pago Remoto" (Pago Presencial en la documentación ), donde la solicitud se origina en un sistema externo (el sitio web del comercio) y la transacción se procesa físicamente en un dispositivo POS de TUU.cl. La documentación destaca que el    

endpoint de creación de pagos utiliza un concepto de idempotencia, lo que permite que una solicitud se pueda repetir de manera segura sin causar efectos secundarios no deseados, como la duplicación de transacciones. Esto es una característica de diseño crucial para la fiabilidad en entornos de red con reintentos o fallos de conexión.

3.2 Análisis del Endpoint de Consulta de Pago

Para complementar el flujo de creación, la API proporciona un endpoint de consulta que permite a los desarrolladores verificar el estado de una solicitud de pago. Este endpoint es GET https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentReques/:idempotency-key. La función de este    

endpoint es permitir al sistema del comercio recuperar el estado final de una transacción que se inició previamente, utilizando la clave de idempotencia generada durante la creación. Este mecanismo es un componente fundamental para un flujo de pago híbrido y puede servir como un mecanismo de respaldo si la comunicación asincrónica falla.

3.3 El Modelo de Pago: Una Distinción Crítica

Un análisis detenido de la documentación revela una distinción importante en la nomenclatura. El endpoint y el flujo de trabajo descritos se encuentran bajo la sección "Pago Presencial" y se refieren a un "pago remoto" que se procesa en un dispositivo POS físico. Este es un punto crítico, ya que la documentación para "TUU Pago Online," que un desarrollador podría esperar para un    

checkout de comercio electrónico, está explícitamente marcada como "próximamente".   

La implicación de esta arquitectura es que la API actual de TUU.cl no proporciona un servicio de pasarela de pago puramente en línea, sino un modelo que requiere la intervención de un dispositivo físico (el POS) para completar la transacción. Esto impone una limitación arquitectónica significativa, ya que la integración no es un simple proceso de redirección web, sino un flujo que depende de la interacción entre un sistema de punto de venta y una aplicación web. Los desarrolladores deben tener en cuenta que el pago no se completa directamente en el sitio web del cliente, sino en el POS, lo que hace que los mecanismos de notificación de estado sean de vital importancia.

Tabla 1: Resumen de Endpoints Críticos y su Función

Nombre del Endpoint	Método HTTP	URL Completa	Descripción de la Función	Requerimientos de Autenticación
Creación de Pago	POST	https://integrations.payment.haulmer.com/PaymentRequest/Create	Inicia una solicitud de pago en un dispositivo TUU POS.	
X-API-Key en el encabezado   
Consulta de Pago	GET	https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentReques/:idempotency-key	Consulta el estado de una solicitud de pago existente utilizando su clave de idempotencia.	
X-API-Key en el encabezado   
4. Autenticación y Gestión de API Key
La autenticación para interactuar con los endpoints de la API de TUU.cl es un proceso sencillo y directo. Todas las solicitudes deben incluir una clave de autenticación única del comercio, conocida como API Key. Esta clave se transmite en el encabezado de las solicitudes HTTP bajo el nombre    

X-API-Key.   

La documentación proporciona una guía clara y precisa para obtener la API Key. El proceso se realiza a través del panel de administración del comercio, denominado "Espacio de Trabajo". Los pasos son los siguientes:   

Acceder al menú principal y seleccionar "Pagos".   
En el menú lateral, elegir "Configuración".   
Seleccionar "API" en el submenú para acceder a la página de generación de claves.   
Localizar el campo API KEY y hacer clic en el botón "Generar api key".   
Una vez generada, la clave es visible únicamente para el propietario de la cuenta y se debe copiar de inmediato, ya que no se puede recuperar posteriormente.   
Para mantener la seguridad de la integración, se recomienda encarecidamente que la API Key no se almacene en el código fuente de la aplicación, en archivos de configuración planos o en repositorios de código. En su lugar, se deben utilizar almacenes de secretos seguros (como un vault o un gestor de secretos en la nube) y acceder a la clave a través de variables de entorno para su uso en producción.

5. Estructura de la Solicitud y Manejo de Errores
5.1 Parámetros de la Solicitud

La documentación de la API detalla varias validaciones y restricciones para los campos que se envían en la solicitud de creación de pago. Se requiere que el Amount (monto de la transacción) se encuentre entre los valores de 100 y 99999999. El    

Device (dispositivo) debe ser una cadena válida que corresponda al número de serie de un dispositivo registrado. En el caso de los montos exentos (exemptAmount), existen reglas específicas según el tipo de documento tributario electrónico (DTE Type), como la exigencia de un monto exento igual al monto total si el DTE Type es 99. Los campos personalizados (   

Custom Fields) están limitados a un máximo de 5, con una longitud combinada de 28 caracteres para el nombre y el valor, y no permiten caracteres especiales como & o /.   

5.2 Estructura de la Respuesta de Errores

La API de TUU.cl implementa un sistema de códigos de error consistente que ayuda a los desarrolladores a identificar y gestionar los problemas de manera programática. Los errores se presentan con un prefijo MR-XXX, acompañado de un tipo de error, un mensaje y una descripción. Por ejemplo, un error    

MR-100 indica que el dispositivo asociado a la API Key no existe, mientras que MR-110 señala que el monto de la transacción es inferior al mínimo permitido. Esta estructura de respuesta para errores es una buena práctica de diseño de API, ya que permite la creación de una lógica de manejo de errores predecible en el código del cliente.   

Tabla 2: Códigos de Error (MR-XXX) y sus Descripciones

Código de Error	Tipo de Error	Mensaje de Error	Descripción Detallada
MR-100	Dispositivo	Device for API-Key doesn't exist	El dispositivo asociado a la API-Key no existe.
MR-110	Campo de entrada	Transaction Amount is less than allowed minimum	El monto de la transacción es inferior al mínimo permitido.
MR-120	Campo de entrada	Transaction Amount is more than allowed	El monto de la transacción supera el máximo permitido.
MR-130	Campo de entrada	DTE type not recognized	El tipo de DTE proporcionado no es reconocido por el sistema.
MR-000	Autorización	Not Authorized	El usuario no está autorizado para realizar esta operación.
6. Mecanismos de Comunicación Asincrónica: Webhooks y Callbacks
En una integración de pagos, los webhooks (o callbacks) son un mecanismo esencial que permite que la pasarela de pago notifique al sistema del comercio, de forma asincrónica, el resultado final de una transacción (por ejemplo, si fue exitosa, fallida o pendiente). Esto libera al sistema del comercio de la necesidad de estar consultando activamente el estado del pago y asegura que la información se reciba de manera oportuna y fiable.   

La documentación de TUU.cl hace referencia a la "Recepción del callback" , lo que sugiere que este mecanismo debería ser un componente del flujo de integración. Sin embargo, la URL que debería contener la información detallada para este proceso (   

https://developers.tuu.cl/docs/recepcion-del-callback) es inaccesible. La investigación adicional de la documentación de TUU.cl no arrojó resultados para la configuración de    

webhooks, y las búsquedas genéricas sobre este tema para otras plataformas de pago no son directamente aplicables a la API de TUU.   

La falta de documentación sobre webhooks para la API de TUU.cl presenta un riesgo arquitectónico significativo. Sin un mecanismo de notificación asincrónica, el único método para determinar el estado de una transacción es a través del endpoint de consulta (GET /...). Esto obliga al sistema del comercio a implementar un mecanismo de    

polling, donde se consulta el estado de la transacción repetidamente a intervalos regulares.

La dependencia del polling para la confirmación de pagos puede llevar a varias ineficiencias y problemas:

Ineficiencia de Recursos: La consulta constante de la API consume recursos del servidor y puede impactar el límite de solicitudes.

Latencia en la Actualización: Puede haber un retraso entre el momento en que se completa la transacción y el momento en que el sistema del comercio actualiza el estado, lo que podría afectar la experiencia del usuario o el flujo de trabajo de la venta.

Riesgo de Pérdida de Estado: Un fallo en el mecanismo de polling o una interrupción del servicio puede resultar en la pérdida de la información del estado de la transacción, dejando pedidos en un estado indeterminado.

En este contexto, la comunidad de desarrolladores de TUU.cl en Slack  podría convertirse en un recurso crucial para obtener información no oficial o asistencia para resolver problemas relacionados con los    

webhooks u otros aspectos no documentados del flujo de pago.

7. Flujo de Redirección y Retorno del Resultado
El flujo de pago remoto de TUU.cl implica que, una vez que el pago es procesado en el dispositivo POS, "se retorna el resultado al sitio web o sistema que realizó la solicitud". Esta afirmación es un componente vital del flujo de pago, ya que es el mecanismo por el cual la aplicación del cliente final es informada sobre el resultado de la transacción.   

A pesar de esta mención, la documentación no proporciona detalles explícitos sobre la naturaleza de esta redirección. No se especifica qué URL se utiliza para el retorno (por ejemplo, una success_url o failure_url), ni qué parámetros de la transacción (como el ID del pago, el monto o el estado final) se incluyen en la URL o en el cuerpo de la respuesta. La investigación no encontró ningún fragmento que detallara los parámetros de redirección o la estructura de la respuesta de retorno.

La ausencia de esta información introduce una incertidumbre considerable en el desarrollo de la interfaz de usuario y la lógica de negocio. Un desarrollador no puede saber de antemano qué información recibirá en el retorno, lo que dificulta la creación de páginas de confirmación de pedido o el manejo de errores en el frontend. Esta falta de claridad obliga al equipo de desarrollo a inferir el comportamiento del sistema a través de pruebas de integración exhaustivas, lo que aumenta el tiempo y el costo del desarrollo y el riesgo de fallos en producción.

8. Consideraciones Adicionales y Gaps Documentales
La revisión de la documentación de TUU.cl y el ecosistema de desarrolladores revela varias áreas de inmadurez. La falta de recursos estándar de la industria es notable; a diferencia de otras plataformas de pago, no se ha encontrado un repositorio oficial de GitHub  o una colección de Postman predefinida. Estos recursos son herramientas cruciales para la adopción de una API, ya que permiten a los desarrolladores explorar y probar rápidamente la funcionalidad sin tener que escribir código desde cero. Su ausencia puede sugerir un enfoque menos prioritario en la experiencia del desarrollador o que la plataforma aún no ha alcanzado una etapa de madurez en su ecosistema de API.   

Además de la falta de documentación sobre webhooks y redirección, la documentación de TUU.cl menciona que las APIs para "TUU Pago Online," "TUU Facturación Electrónica" y "TUU Firma Electrónica" están "próximamente". Esto indica que funcionalidades clave para una integración completa y moderna aún no están disponibles o están en una fase de desarrollo temprana. Los planificadores de proyectos deben tener en cuenta que las capacidades de la plataforma están limitadas a su oferta actual y que las funcionalidades prometidas podrían no estar disponibles en el corto o mediano plazo.   

9. Conclusiones y Recomendaciones de Integración
En resumen, la API de TUU.cl para "Pago Remoto" presenta un diseño sólido en los aspectos documentados. La arquitectura con endpoints claros, el uso de una API Key para autenticación y el soporte para la idempotencia son puntos fuertes que facilitan la integración técnica.   

Sin embargo, las lagunas documentales identificadas son un factor de riesgo significativo que debe ser gestionado. El principal desafío es la ausencia de detalles sobre la recepción de callbacks y los parámetros de redirección. Esta carencia implica que el sistema del comercio no puede depender de notificaciones asincrónicas y, por lo tanto, debe implementar una estrategia de    

polling para verificar el estado de las transacciones.   

Recomendaciones para una Integración Exitosa:

Planificación del Flujo de Polling: El equipo de desarrollo debe diseñar e implementar una lógica robusta de polling para consultar el estado de las transacciones en el endpoint GET /... de la API. Esta lógica debe incluir mecanismos de reintento con    

backoff exponencial para gestionar las ineficiencias de la red y evitar saturar el sistema.

Validación del Flujo de Retorno: Se recomienda realizar una validación exhaustiva en un entorno de pruebas para inferir el comportamiento del flujo de redirección, incluyendo la estructura de la URL de retorno y los parámetros que se incluyen. Esta información inferida es crucial para el diseño de la interfaz de usuario posterior al pago.

Uso de la Comunidad de Slack: Dada la falta de documentación detallada, la comunidad de desarrolladores en Slack de TUU.cl  debe ser considerada como una fuente activa de soporte técnico, tanto para resolver problemas como para obtener información no oficial sobre comportamientos de la API.   

Gestión de las Expectativas: Es importante que el equipo de proyecto y las partes interesadas comprendan que las funcionalidades como el "pago online" puramente basado en la web no están actualmente disponibles y podrían no estarlo en el futuro cercano, lo cual limita el alcance de la integración a un flujo híbrido que requiere un dispositivo POS.   
En conclusión, la integración con la API de TUU.cl es técnicamente factible para el flujo de pago remoto, pero exige una metodología de desarrollo más proactiva y un enfoque en la mitigación de riesgos para compensar las deficiencias en la documentación.

Pago remoto
¿Te interesa integrar pago remoto a tus servicios? Sé parte de nuestros clientes y adquiere un dispositivo POS con nosotros para comenzar 🙌.
🤔 ¿Qué es pago remoto?

El concepto de pago remoto implica que, a través de un webservice, se envía una solicitud de pago a dispositivos POS (Point of Sale), lo que activa de manera remota un flujo en el que el POS se inicia con un conjunto de datos específicos para realizar una transacción con el tarjetahabiente. El POS procesa el pago, y una vez completado el flujo (ya sea exitoso o fallido), se retorna el resultado al sitio web o sistema que realizó la solicitud.

A continuación, se presenta un diagrama de alto nivel que ilustra el flujo del proceso de pago remoto:



🚧
Por favor, tener en consideración los sgtes. puntos:

 Confirmar la carga y activación de su POS en Espacio de Trabajo.
 Obtener su API Key desde Espacio de Trabajo para hacer uso de los endpoints de Pago Remoto.
 Generada la solicitud de pago, confirme su recepción en el "Modo Integración" del POS.
🔐 Autenticación y Modo de Integración

Clave API

Toda interacción con los endpoints requiere que el comercio posea una API Key válida. Esta API Key se obtiene directamente desde el Espacio de Trabajo del comercio y es única por comercio.

¿Cómo obtener la API Key?

Dirígete al panel de administración de tu cuenta en el Espacio de Trabajo (Workspace), sección Pagos>Configuración>API.

Modo Integración

Antes de poder interactuar con los endpoints de la API, el terminal debe estar configurado en modo integración. Este modo permite que el terminal quede a la espera de solicitudes de pago externas. Para habilitar este modo, sigue estos pasos:

Ingrese a la aplicación Pago, realice los pasos que le solicita y posteriormente, se activará una vista de "Activa tu dispositivo".
Diríjase a tu Espacio de trabajo, selecciona la sección Pagos e ingresa a la opción Pagos Haulmer (barra lateral izquierda).
Ingresa el código que aparece en tu POS y ¡Activa tu dispositivo!
Finalmente, seleccione el menú tipo "hamburguesa" y haga clic en la opción "Modo Integración".
Con estos pasos su dispositivo esta listo para recibir solicitudes de pago de forma remota.

📖 Diccionario de datos

Estados de las Solicitudes de Pago

Cada solicitud de pago pasa por varios estados durante su ciclo de vida. A continuación, se describen los posibles estados que puede tener una solicitud de pago:

Pending: La solicitud ha sido recibida, pero aún no ha sido enviada al Punto de Venta.
Sent: El intento de pago ha sido enviado al Punto de Venta.
Canceled: El intento de pago fue cancelado en el Punto de Venta.
Processing: El sistema está validando el pago con los adquirentes y emisores correspondientes.
Failed: El sistema rechazó o falló la transacción debido a un error o validación negativa.
Completed: La transacción fue validada exitosamente, y el pago ha sido completado.
Tabla de conversión de solicitud

Status	Valor
Pending	0
Sent	1
Canceled	2
Processing	3
Failed	4
Completed	5
Tabla de conversión DTE

DTE	Valor
0	Comprobante afecto. Por defecto
33	Factura afecta
34	Factura exenta
48	Comprobante afecto
99	Comprobante exento
API's

Creación de pago (SIN idempotencia):


POST <https://integrations.payment.haulmer.com/RemotePayment/v2/Create>
Header: X-API-Key: XXX
Consulta solicitud de pago (SIN idempotencia):


GET <https://integrations.payment.haulmer.com/PaymentRequest/:id>
Header: X-API-Key: XXX
Creación de pago (CON idempotencia):


POST <https://integrations.payment.haulmer.com/PaymentRequest/Create>
Header: X-API-Key: XXX
Consulta solicitud de pago (CON idempotencia):


GET <https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentReques/:idempotency-key>
Header: X-API-Key: XXX
Campos de entrada

A continuación se presenta una tabla que describe los principales campos de entrada requeridos para la solicitud de pago en el flujo de integración con el sistema de pago remoto. Estos campos son esenciales para procesar la transacción y deben incluirse en la solicitud.

🚧
Campo con * corresponde al flujo con idempotencia (v2)
Nombre	Tipo	Descripción	Ejemplo
idempotencyKey*	string	Identificador único de la solicitud de pago.	KEY-01
amount	int	Monto total de la transacción.	1000
device	string	Número de serie del dispositivo POS.	TJ44245N20440
cashbackAmount*	int	Vuelto.	500
tipAmount*	int	Propina.	10
paymentMethod*	int	Tipo de método de pago, sea crédito (1) o débito (2)	1
description	string	Breve descripción de la transacción.	"Pago de servicios"
dteType	int	Tipo de documento tributario electrónico.	99
extradata	object	Información adicional relevante para la transacción, incluyendo montos exentos y campos personalizados.	
Campos del extra data

Nombre	Tipo	Descripción	Ejemplo
exempAmount	int	Monto exento de impuestos que aplica a la transacción.	1000
customFields	array	Lista de campos personalizados que contiene información adicional relevante.	
sourceName	string	Nombre de origen de la transacción.	"Plataforma de pagos"
sourceVersión	string	Versión del origen de la solicitud.	"v1.0.0"
Campos del CustomFields

Nombre	Tipo	Descripción	Ejemplo
name	string	Nombre del campo personalizado.	"Contacto"
value	string	Valor del campo personalizado	"9 2321 4244"
print	boolean	Indica si el campo debe imprimirse en el comprobante	true
Tabla de errores

La siguiente tabla describe los errores que pueden generarse durante el proceso de pago. Cada error incluye una descripción y un código que permite identificar el problema y tomar las acciones correctivas necesarias para su resolución por parte del equipo técnico.

🚧
Campo con * corresponde al flujo con idempotencia (v2)
Código	Tipo	Mensaje	Descripción
MR-100	Dispositivo	Device for API-Key doesn't exist	El dispositivo asociado a la API-Key no existe.
MR-110	Campo de entrada	Transaction Amount is less than allowed minimum	El monto de la transacción es inferior al mínimo permitido.
MR-120	Campo de entrada	Transaction Amount is more than allowed	El monto de la transacción supera el máximo permitido.
MR-130	Campo de entrada	DTE type not recognized	El tipo de DTE proporcionado no es reconocido por el sistema.
MR-140	Campo de entrada	No exempt amount found for DTE Type 99	No se ha proporcionado un monto exento para el tipo de DTE 99.
MR-141	Campo de entrada	Exempt amount not equal to transaction amount	El monto exento no coincide con el monto total de la transacción.
MR-150	Campo de entrada	Exempt Amount is not less than transaction amount	El monto exento es mayor o igual al monto total de la transacción.
MR-151	Campo de entrada	Exempt Amount is invalid	El monto exento proporcionado no es válido.
MR-000	Autorización	Not Authorized	El usuario no está autorizado para realizar esta operación.
MR-160	Solicitud de pago	Payment Request doesn't exist	No se encontró una solicitud de pago con el ID proporcionado.
MR-161	Dispositivo	Device by SN not found	No se encontró un dispositivo con el número de serie proporcionado.
MR-170	Servicio	Error with Database	Ocurrió un error al intentar acceder a la base de datos.
MR-180	Cola de Solicitudes	Payment Request Queue for the device is full	La cola de solicitudes de pago para el dispositivo está llena.
I-04	Campos Personalizados	ExtraData String has invalid characters	El campo ExtraData contiene caracteres no válidos.
INT-MIDDLEWARE-429	Límite de Solicitudes	API Quota Exceeded! Quota: 0 per 1, Try Again in 2 seconds	Se ha excedido la cuota de solicitudes de la API.
I-02	Campos Personalizados	Custom field length invalid	La longitud de un campo personalizado es inválida.
I-03	Campos Personalizados	Custom field length invalid	La longitud de un campo personalizado es inválida.
KEY-002 *	Autenticación	API Key is missing in the request header	Falta la clave API en la cabecera de la solicitud.
KEY-003 *	Autenticación	Invalid API Key	La clave API proporcionada no es válida.
RP-000 *	Idempotencia	Invalid Idempotency Key	La clave de idempotencia proporcionada es inválida o está mal formada.
RP-001 *	Campo de entrada	Idempotency Key length must be between 1 and 36 characters	La longitud de la clave de idempotencia debe estar entre 1 y 36 caracteres.
RP-003 *	Campo de entrada	The characters entered are invalid	Los caracteres ingresados en el nombre de origen son inválidos.
RP-004 *	Campo de entrada	Invalid characters in source version	Los caracteres ingresados en la versión de origen son inválidos.
RP-005 *	Campo de entrada	No exempt amount for specific DTE types	El monto exento no esta permitido para el tipo de DTE ingresado.
RP-006 *	Campo de entrada	Exempt amount does not match total	El monto exento no coincide con el total.
RP-007 *	Campos Personalizados	Reserved custom field name	El campo personalizado tiene un nombre reservado.
RP-008 *	Campo de entrada	Missing payment method	Falta el método de pago.
RP-010 *	Campos Personalizados	Maximum custom fields exceeded	La cantidad de campos personalizados supera el máximo permitido.
RP-011 *	Campo de entrada	Exempt amount must be less than the total amount	El monto exento debe ser menor que el monto total.
RP-012 *	Campo de entrada	Exempt amount exceeds transaction amount	El monto exento excede el monto total. Máximo permitido 99999999.
RP-015 *	Campo de entrada	Invalid length	Longitud inválida. La longitud debe ser entre 1 y 28.
RP-017 *	Campo de entrada	Amount exceeds maximum allowed	El monto excede el máximo permitido
RP-018 *	Campo de entrada	Tip amount must be less than the total amount	La propina debe ser menor que el monto
RP-019 *	Campo de entrada	Tip amount exceeds maximum allowed	La propina excede el máximo permitido. Máximo 99999999.
RP-020 *	Campo de entrada	Method not permitte	Método de pago no permitido.
RP-021 *	Campo de entrada.	Cashback amount must be less than the total amount	El vuelto debe ser menor que el monto total.
RP-022 *	Campo de entrada	Cashback amount exceeds maximum allowed	El vuelto excede el máximo permitido.
RP-025 *	Campo de entrada	Invalid payment method	Método de pago no válido.
RP-026 *	Campos personalizados	Duplicate custom field names	Se encontraron campos personalizados con nombres duplicados.
RP-027 *	Campo de entrada	All amounts exceed the maximum allowed	La suma de los montos supera el máximo permitido.
RP-028 *	Campo de entrada	Invalid amount, must be equal to or greater than 100	El monto debe ser mayor o igual a 100.
RP-029 *	Pos	Device configuration not found	Configuración del dispositivo no encontrada
RP-030 *	Pos	Device settings do not allow tip entry	La configuración del dispositivo no permite la opción de propina.
RP-031 *	Pos	The device configuration does not allow the deposit of cashback	La configuración del dispositivo no permite la opción de vuelto.
RP-032 *	Pos	Device settings do not support the payment method entered	La configuración del dispositivo no permite la opción del método de pago ingresado.
RP-100 *	Autenticación	Unauthorized access. Authentication required.	Acceso no autorizado. Se requiere autenticación.
RP-101 *	Autenticación	Invalid or missing Payment Account ID	Cuenta no encontrada.
RP-102 *	Autenticación	JWT validation failed	JWT inválido.
RP-200 *	Idempotencia	Payment request not found for the provided idempotency key	La solicitud de pago no existe para la clave de idempotencia proporcionada
MR-191 *	Idempotencia	The identifier provided is already in use.	La clave de idempotencia ya está en uso para otra solicitud.
MR-203 *	Solicitud de pago	Payment request is in process	La solicitud de pago está actualmente en proceso.
RP-005 *	Campo de entrada	No exempt amount for specific DTE types	No se especificó un monto exento para los tipos de DTE específicos.
RP-006 *	Campo de entrada	Exempt amount does not match total	El monto exento no coincide con el total de la transacción.
☝️ Consideraciones adicionales

Idempotencia para flujo PaaS

En el sistema de pagos, la idempotencia asegura que múltiples solicitudes con el mismo propósito no creen transacciones duplicadas. El proceso de idempotencia se maneja automáticamente por el sistema y no requiere ninguna acción por parte del cliente.

Generación de la idempotencia

Para crear una nueva solicitud de pago el cliente debe enviar la clave idempotencia (obligatorio).
El idempotencyKey debe ser único para cada solicitud y se utiliza para rastrear el estado de la transacción.
Prevención de solicitudes duplicadas

Si se envía la misma solicitud de pago varias veces (por ejemplo, debido a un problema de red o un timeout), el sistema verifica si existe un idempotencyKey asociado a una solicitud anterior dentro de los últimos 10 minutos.
Si el idempotencyKey ya existe y la solicitud anterior está en estado Completed o Sent, el sistema no volverá a procesar la transacción.
Si la solicitud está en estado Processing, el sistema indicará que la transacción aún está en proceso, evitando múltiples intentos de pago.
Límite de Solicitudes

Para evitar la saturación del sistema y del terminal, existe un control de flujo sobre las solicitudes de pago. Cada terminal tiene las siguientes restricciones:

Máximo de solicitudes pendientes: Un terminal puede tener un máximo de 5 solicitudes pendientes en cola.
Límite de solicitudes por minuto: Se puede enviar 1 solicitud por minuto. Si se excede este límite, el sistema devolverá un error 429 Too Many Requests.
Validaciones de campos

Amount: El monto de la solicitud de pago debe estar entre 100 y 99999999.
Device: El número de serie del dispositivo asociado debe ser una cadena válida y registrada.
Montos Exentos:
Si el dteType es no afecta, se requiere un exemptAmount igual al Amount.
Si el dteType es afecta, se requiere un exemptAmount menor a Amount
Campos Personalizados (Custom Fields):
Máximo 5 campos personalizados.
Longitud combinada de name y value no debe exceder los 28 caracteres.
Caracteres especiales no permitidos "&", "/".
No utilice caracteres no ASCII.
DTE Type: Debe ser uno de los valores permitidos: 0, 33, 48 o 99.

🚀 ¿Listo para integrar?

Diríjase a la sección API Reference, específicamente Integración Pago Remoto.

☎️ Contacto y Soporte

Si necesitas soporte adicional o tienes dudas sobre el proceso, nuestro equipo está disponible para ayudarte.

Formulario de contacto
Updated about 1 month ago

TUU - Página de integraciones
Comprobante de pago y modelo de emisión
Did this page help you?
TABLE OF CONTENTS
🤔 ¿Qué es pago remoto?
🔐 Autenticación y Modo de Integración
Clave API
Modo Integracióno
📖 Diccionario de datos
Estados de las Solicitudes de Pago
Tabla de conversión de solicitud
Tabla de conversión DTE
API's
Campos de entrada
Tabla de errores
☝️ Consideraciones adicionales
Idempotencia para flujo PaaS
Límite de Solicitudes
Validaciones de campos
🚀 ¿Listo para integrar?
☎️ Contacto y Soporte