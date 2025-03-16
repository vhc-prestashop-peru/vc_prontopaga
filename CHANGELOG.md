# Changelog

Todas las novedades y cambios importantes de este proyecto se documentan aquí.

## [1.0.0] - 17/03/2025

### Añadido
- Primera versión del módulo **ProntoPago** para PrestaShop.
- Integración con la API de ProntoPago mediante cURL (sin Composer).
- Clase `ProntoPagoApiManager` para gestionar peticiones HTTP (GET/POST) a la API.
- Clase `ProntoPagoHelper` que provee métodos de alto nivel (`getBalance`, `getPaymentMethods`, `createNewPayment`) y firma de datos (`generateSignature`).
- Estructura de carpetas recomendada para tratar el código como un mini SDK dentro del módulo.

### Cambiado
- N/A

### Eliminado
- N/A