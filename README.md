# MTG Collection Manager

Aplicación web para gestionar colecciones de cartas de Magic: The Gathering.

## Stack

* Backend: PHP + Slim 4
* Base de datos: MySQL
* Frontend: HTML, CSS, JavaScript
* Servicio de reconocimiento: Python (FastAPI)
* Contenedores: Docker + Docker Compose

## Requisitos

* Docker Desktop (incluye Docker y Docker Compose): https://www.docker.com/products/docker-desktop/

## Instalación

### Obtener el proyecto

Puedes clonar el repositorio:

```
git clone https://github.com/Gunval-AI/mtg-collection-manager.git
cd mtg-collection-manager
```

O descargarlo como ZIP desde GitHub y descomprimirlo.

---

### Crear archivo `.env`

En Linux / Mac:

```
cp .env.example .env
```

En Windows (CMD o PowerShell):

```
copy .env.example .env
```

---

### Levantar el proyecto

```
docker compose up --build
```

## Acceso

* Frontend: http://localhost:8080/frontend/

## Base de datos

Se inicializa automáticamente al levantar Docker:

* `01-schema.sql` → estructura
* `02_seed.sql` → datos base

## Funcionalidades principales

* Autenticación de usuarios (registro, login, logout)
* Gestión de colecciones
* Gestión de copias de cartas
* Búsqueda de cartas e impresiones
* Reconocimiento de cartas mediante imagen

## Arquitectura

El backend sigue una arquitectura por capas:

* Controllers → gestionan las peticiones HTTP
* Services → lógica de negocio
* Repositories → acceso a base de datos
* DTOs → transporte de datos

## Notas

* Configuración mediante `.env`
* El servicio de reconocimiento funciona como microservicio independiente
* La base de datos se crea automáticamente al iniciar Docker

## Ejecución desde cero

1. Instalar Docker Desktop
2. Clonar el repositorio o descargarlo como ZIP
3. Crear `.env`
4. Ejecutar `docker compose up --build`
5. Acceder a http://localhost:8080/frontend/
