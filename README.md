# UbikGo Core API (`vial-core-api`)

API transaccional y robusta para la plataforma de asistencia vial y gestión de grúas **UbikGo**, desarrollada en **PHP 8.5+ puro** con **Arquitectura Hexagonal (Ports & Adapters)** y **Domain-Driven Design (DDD)** sin dependencias pesadas de terceros (Zero Composer en runtime).

---

## 🏛️ Arquitectura del Sistema

```
vial-core-api/
├── bin/                        # Binarios de testing (phpunit.phar)
├── bootstrap/                  # Contenedor de inyección de dependencias (PSR-11 nativo)
│   └── container.php
├── config/                     # Configuraciones del entorno
├── database/                   # Definición de esquemas MariaDB
├── public/                     # Punto de entrada HTTP y activos públicos
│   ├── docs/                   # Swagger UI interactivo (/docs/index.html)
│   ├── index.php               # Front Controller
│   └── openapi.yaml            # Contrato canónico OpenAPI 3.0.3
├── scripts/                    # Scripts de mantenimiento (migraciones, seeders, smoke tests)
│   ├── migrate.php             # Ejecutor de migraciones de base de datos
│   └── seed.php                # Seeder de clases, marcas y modelos de vehículos
├── src/
│   ├── Application/            # Casos de uso (Use Cases) y DTOs organizados por Subdominio
│   │   ├── Auth/               # Login, Registro, Tokens, Recuperación, KYC & OCR
│   │   ├── Users/              # Perfil y Libreta de Direcciones Guardadas
│   │   ├── Vehicles/           # Registro, Escaneo OCR y Catálogo de Vehículos
│   │   ├── Contracts/          # Planes, Membresías y Checkout de Contratos Anuales
│   │   └── Payments/           # Procesamiento C2P, Pago Móvil Plus y Webhooks Bancaribe
│   ├── Controllers/            # Adaptadores primarios HTTP (REST Controllers)
│   ├── Core/                   # Micro-framework nativo (Kernel, Router, Request, Response)
│   ├── Domain/                 # Núcleo del negocio organizado por Subdominios (Bounded Contexts)
│   │   ├── Shared/             # Primitivas transversales (Uuid, Money, Coordinates, Currency)
│   │   ├── Auth/               # Autenticación, RBAC (Roles/Permissions), Tokens y Documentos KYC
│   │   ├── Users/              # Usuario, Direcciones y Value Objects de Identidad
│   │   ├── Vehicles/           # Vehículos, Catálogo Maestro y Servicio de Matching OCR
│   │   ├── Contracts/          # Planes y Contratos de Servicio Anual
│   │   └── Payments/           # Órdenes Polimórficas, Pagos, Tasas BCV y Puertos Bancaribe
│   ├── Infrastructure/         # Adaptadores secundarios (MariaDB, Redis, Bancaribe, OCR)
│   │   ├── Database/
│   │   │   ├── Migrations/     # Migraciones transaccionales (m000001 - m000023)
│   │   │   ├── Repositories/   # Repositorios MariaDB/Redis por Subdominio (Auth, Users, Vehicles, Contracts, Payments)
│   │   │   └── Seeders/        # Seeders de roles, catálogo automotriz, bancos y planes
│   │   ├── Http/
│   │   ├── Logging/            # IntegrationLogger para auditoría de servicios de terceros
│   │   └── Services/
│   ├── Middlewares/            # Filtros de petición (JWT Auth, RBAC, KYC verification)
│   └── Routes/                 # Definición de rutas (/api/v1)
├── storage/                    # Archivos subidos KYC y logs de integraciones (integrations.jsonl)
└── tests/                      # Suite de pruebas automatizadas PHPUnit
    ├── Doubles/                # Fakes y repositorios en memoria (InMemory)
    └── Unit/                   # Pruebas unitarias de Dominio, Aplicación y Middlewares
```

---

## 🚀 Requisitos del Entorno

- **PHP**: 8.5+ con extensiones `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `openssl`, `sodium`.
- **Base de Datos**: MariaDB 10.11+ / MySQL 8.0+.
- **Caché y Throttling**: Redis 6+ / Memurai (puerto 6379).
- **Microservicio OCR**: Contenedor Docker de extracción de documentos (puerto 8080).
- **Servidor SMTP**: Mailpit / Mailtrap o SMTP corporativo para recuperación de contraseñas.

---

## 🛠️ Configuración Inicial

1. **Variables de Entorno**:
   Copiar `.env.example` a `.env` y configurar las credenciales:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ubikgo_core
   DB_USERNAME=root
   DB_PASSWORD=

   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379

   OCR_SERVICE_URL=http://127.0.0.1:8080
   JWT_SECRET=super_secret_jwt_key_32_characters_minimum
   ```

2. **Ejecutar Migraciones de Base de Datos**:
   ```bash
   php scripts/migrate.php
   ```

3. **Ejecutar Seeders del Catálogo Automotriz**:
   ```bash
   php scripts/seed.php
   ```

4. **Iniciar Servidor de Desarrollo**:
   ```bash
   php -S 0.0.0.0:8088 server.php
   ```

---

## 📖 Documentación OpenAPI / Swagger

La documentación interactiva completa con Swagger UI está disponible directamente en el navegador:
- **URL**: `http://localhost:8088/docs/index.html`
- **Especificación OpenAPI 3.0.3**: `http://localhost:8088/openapi.yaml`

---

## 📡 Endpoints Implementados (API v1)

### 1. Sistema
| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/v1/ping` | Smoke test / Comprobación de salud |
| `GET` | `/api/v1/teapot` | Verificación de conformidad RFC 2324 |

### 2. Autenticación y Perfil
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/scan-identity` | No | Pre-escaneo OCR de Cédula de Identidad con autocompletado y validación previa |
| `POST` | `/api/v1/register` | No | Registro multi-rol (`client`, `driver`, `seller`), validación KYC y corrección manual |
| `POST` | `/api/v1/login` | No | Inicio de sesión con Bcrypt y emisión de JWT + Refresh Token (Rate Limit: 5/min) |
| `GET`  | `/api/v1/me` | Bearer | Consulta del perfil del usuario autenticado, roles y balance de billetera |
| `POST` | `/api/v1/refresh-token` | No | Renovación y rotación atómica de tokens |
| `POST` | `/api/v1/logout` | Bearer | Revocación inmediata del Refresh Token en base de datos |
| `POST` | `/api/v1/forgot-password` | No | Envío de código OTP de 6 dígitos vía SMTP (TTL: 15 min en Redis) |
| `POST` | `/api/v1/reset-password` | No | Restablecimiento de contraseña con OTP y revocación de sesiones |

### 3. Documentos KYC y Auditoría Admin
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/documents/upload` | Bearer | Carga o reenvío de documentos de identidad o vehiculares |
| `GET`  | `/api/v1/documents/kyc-status` | Bearer | Estado consolidado de verificación KYC del usuario |
| `GET`  | `/api/v1/admin/documents?status=PENDING` | Bearer (`audit_kyc`) | Listado de documentos KYC pendientes de revisión por un administrador |
| `POST` | `/api/v1/admin/documents/{id}/review` | Bearer (`audit_kyc`) | Aprueba (`VERIFIED`) o rechaza (`REJECTED`) un documento (activa el vehículo automáticamente) |

### 4. Direcciones Guardadas (FR-19)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `GET`    | `/api/v1/addresses` | Bearer | Listado de direcciones favoritas con coordenadas GPS |
| `POST`   | `/api/v1/addresses` | Bearer | Creación de nueva dirección favorita |
| `DELETE` | `/api/v1/addresses/{id}` | Bearer | Eliminación de dirección favorita por UUID v7 |

### 5. Catálogo de Vehículos (Lectura con Caché Redis)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `GET` | `/api/v1/vehicles/catalog/classes` | No | Lista de clases vehiculares maestras (Automóvil, Camión, Moto, etc.) |
| `GET` | `/api/v1/vehicles/catalog/brands?q=toy` | No | Búsqueda y listado de marcas automotrices |
| `GET` | `/api/v1/vehicles/catalog/models?brand_id=531&q=cor` | No | Búsqueda y listado de modelos por marca |

### 6. Gestión de Vehículos (FR-18)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `POST`   | `/api/v1/vehicles/scan` | Bearer | Escaneo OCR de Carnet de Circulación o Título con normalización de catálogo |
| `GET`    | `/api/v1/vehicles` | Bearer | Listado de vehículos propios registrados por el usuario |
| `POST`   | `/api/v1/vehicles` | Bearer (RBAC) | Registro de vehículo propio (requiere permiso `register_vehicle`) |
| `DELETE` | `/api/v1/vehicles/{id}` | Bearer | Desactivación lógica (Soft Delete) de vehículo por UUID v7 |

### 7. Planes, Membresías y Tasas BCV (FR-11, FR-13, FR-31)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `GET`    | `/api/v1/plans` | No | Catálogo de planes anuales en USD con conversión dinámica a VES (Redis Cache) |
| `POST`   | `/api/v1/plans` | Bearer (`manage_catalog`) | Crear nuevo plan en USD e invalidar caché |
| `PUT`    | `/api/v1/plans/{id}` | Bearer (`manage_catalog`) | Actualizar plan existente por UUID v7 |
| `DELETE` | `/api/v1/plans/{id}` | Bearer (`manage_catalog`) | Desactivar plan de membresía |
| `GET`    | `/api/v1/banks` | No | Catálogo de 23 bancos nacionales Sudeban (código de 4 dígitos) |
| `GET`    | `/api/v1/exchange-rates` | No | Tasas oficiales de compra/venta BCV (USD y EUR) cacheadas en Redis |
| `POST`   | `/api/v1/exchange-rates/sync` | Bearer (`admin`) | Forzar sincronización de tasas BCV con el API de Bancaribe |

### 8. Contratos, Renovación, Canje y Pasarela Bancaribe (FR-12, FR-14, FR-28, FR-29, FR-30)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/checkout` | Bearer | Genera contrato en `PENDING_PAYMENT` y orden polimórfica con tasa BCV |
| `GET`  | `/api/v1/contracts` | Bearer | Listado de contratos del usuario con timestamps exactos (`starts_at`, `expires_at`) y cupos disponibles |
| `GET`  | `/api/v1/contracts/{id}/pdf` | Público (UUID) / Bearer | Descarga o visualiza el Contrato en PDF (2 páginas con QR y marca de agua dinámica según estado) |
| `POST` | `/api/v1/contracts/{id}/renew` | Bearer | Genera una nueva Orden de Pago anual para renovar un contrato (extiende 1 año y restaura km/viajes) |
| `POST` | `/api/v1/contracts/{id}/redeem` | Bearer | Canjea 1 servicio de grúa y descuenta los kilómetros recorridos sobre un contrato activo |
| `POST` | `/api/v1/contracts/{id}/cancel` | Bearer | Cancela un contrato de servicio (aplica marca de agua `CANCELADO` conservando el sello de pago) |
| `POST` | `/api/v1/admin/contracts/expire-overdue` | Bearer (`admin`) | Ejecuta barrido manual de expiración de contratos cuyo timestamp `expires_at` haya vencido |
| `GET`  | `/api/v1/orders` | Bearer | Historial de órdenes de pago del usuario |
| `POST` | `/api/v1/payments/process` | Bearer | Procesa cobro `c2p` (solo cliente) o `pago_movil` (cliente y vendedora vía Operaciones Plus) y activa/renueva el contrato |
| `POST` | `/api/v1/webhooks/bancaribe` | Firma HMAC | Webhook asíncrono de Bancaribe para confirmación/rechazo de pagos |
| `GET`  | `/api/v1/admin/bank/balances` | Bearer (`admin`) | Consulta de saldos disponibles/bloqueados de las cuentas recaudadoras en Bancaribe |
| `GET`  | `/api/v1/admin/integrations/logs` | Bearer | Visor de logs de integraciones de terceros (`bancaribe`, `ocr`, `mapbox`, `pusher`, `s3`) |

### 9. Módulo Comercial de Vendedoras (`role:seller`)
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `GET`  | `/api/v1/seller/clients/search?identification_card=V-...` | Bearer (`sell_service_contract`) | Busca cliente por cédula (Redis + Índice Único MariaDB) y retorna sus vehículos y contratos |
| `POST` | `/api/v1/seller/clients` | Bearer (`sell_service_contract`) | Registra cliente (OCR o manual), aprueba su cédula (`VERIFIED`) y envía correo con clave aleatoria |
| `POST` | `/api/v1/seller/vehicles` | Bearer (`sell_service_contract`) | Registra vehículo para el cliente y lo aprueba automáticamente (`VERIFIED`) sin revisión de admin |
| `POST` | `/api/v1/seller/contracts` | Bearer (`sell_service_contract`) | Genera contrato preliminar (`NO PAGADO`) y orden anual vinculada a la vendedora (`seller_id`) |
| `GET`  | `/api/v1/seller/contracts?status=EXPIRED` | Bearer (`sell_service_contract`) | Lista contratos vendidos por la vendedora (y contratos vencidos para gestionar renovaciones) |
| `POST` | `/api/v1/seller/contracts/{id}/renew` | Bearer (`sell_service_contract`) | Genera nueva orden anual de renovación para un contrato vencido |

---

## ⏰ Cronjobs Obligatorios del Servidor

> [!IMPORTANT]
> **Requisitos Operativos Indispensables:** El servidor centraliza en segundo plano mediante cronjobs y workers las tareas críticas de sincronización, vigencia y almacenamiento para que ninguna petición de usuario sufra bloqueos:
> 1. **`php scripts/sync_bank_token.php`**: Renueva proactivamente el token OAuth2 de Bancaribe en MariaDB (`bank_tokens`) y Redis (`bancaribe:access_token`).
> 2. **`php scripts/sync_exchange_rates.php`**: Sincroniza las tasas oficiales BCV (USD y EUR) con Bancaribe en MariaDB y Redis e invalida la caché de planes.
> 3. **`php scripts/expire_contracts.php`**: Transiciona automáticamente de `ACTIVE` a `EXPIRED` los contratos cuyo timestamp exacto `expires_at` (`YYYY-MM-DD HH:MM:SS`) haya vencido.
> 4. **`php scripts/process_s3_queue.php`**: Procesa la cola en Redis (`queue:s3_document_uploads`), eliminando metadatos EXIF (NFR-17) y subiendo de forma persistente los documentos a AWS S3 (bucket `ubik-go`).

### Ejecución manual conjunta o individual por CLI:
```bash
php scripts/run_cronjobs.php
# O individualmente:
php scripts/sync_bank_token.php
php scripts/sync_exchange_rates.php
php scripts/expire_contracts.php
php scripts/process_s3_queue.php          # Procesa lote de hasta 50 trabajos
php scripts/process_s3_queue.php --daemon # Modo Daemon continuo en segundo plano
php scripts/enqueue_existing_documents.php # Migra archivos locales existentes en storage/documents hacia S3
```

### Configuración en Linux (`crontab -e` / Supervisor):
```cron
*/25 * * * * /usr/bin/php /ruta/al/proyecto/vial-core-api/scripts/sync_bank_token.php >> /ruta/al/proyecto/vial-core-api/storage/logs/cron.log 2>&1
0 * * * * /usr/bin/php /ruta/al/proyecto/vial-core-api/scripts/sync_exchange_rates.php >> /ruta/al/proyecto/vial-core-api/storage/logs/cron.log 2>&1
*/5 * * * * /usr/bin/php /ruta/al/proyecto/vial-core-api/scripts/expire_contracts.php >> /ruta/al/proyecto/vial-core-api/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /ruta/al/proyecto/vial-core-api/scripts/process_s3_queue.php >> /ruta/al/proyecto/vial-core-api/storage/logs/s3_worker.log 2>&1
```

---

## 🧪 Pruebas Automatizadas

El proyecto cuenta con una cobertura exhaustiva de pruebas unitarias y de arquitectura con PHPUnit:

```bash
.\phpunit.bat --colors=never
```

**Resultado actual:**
```
OK (173 tests, 552 assertions) - 100% Passing
```



