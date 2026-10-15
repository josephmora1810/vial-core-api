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
│   ├── Application/            # Casos de uso (Use Cases) y DTOs de transferencia
│   │   ├── DTOs/
│   │   └── UseCases/
│   ├── Controllers/            # Adaptadores primarios HTTP (REST Controllers)
│   ├── Core/                   # Micro-framework nativo (Kernel, Router, Request, Response)
│   ├── Domain/                 # Núcleo del negocio (Entidades, Value Objects, Enums, Interfaces)
│   │   ├── Entities/
│   │   ├── Enums/
│   │   ├── Exceptions/
│   │   ├── Repositories/       # Puertos de salida (Interfaces)
│   │   ├── Services/           # Servicios de dominio y matching
│   │   └── ValueObjects/       # Value Objects inmutables con validación estricta
│   ├── Infrastructure/         # Adaptadores secundarios (MariaDB, Redis, Microservicio OCR)
│   │   ├── Database/
│   │   ├── Http/
│   │   └── Services/
│   ├── Middlewares/            # Filtros de petición (JWT Auth, RBAC, KYC verification)
│   └── Routes/                 # Definición de rutas (/api/v1)
├── storage/                    # Archivos subidos y temporales de documentos KYC
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

### 3. Documentos KYC
| Método | Endpoint | Auth | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/documents/upload` | Bearer | Carga o reenvío de documentos de identidad o vehiculares |
| `GET`  | `/api/v1/documents/kyc-status` | Bearer | Estado consolidado de verificación KYC del usuario |

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

---

## 🧪 Pruebas Automatizadas

El proyecto cuenta con una cobertura exhaustiva de pruebas unitarias y de arquitectura con PHPUnit:

```bash
.\phpunit.bat --colors=never
```

**Resultado actual:**
```
OK (148 tests, 422 assertions) - 100% Passing
```
