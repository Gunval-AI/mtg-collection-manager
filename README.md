# MTG Collection Manager

Aplicación web para gestionar colecciones de cartas de Magic: The Gathering.

## Requisitos

- Docker
- Docker Compose

Recomendada la descarga de docker desktop: https://www.docker.com/products/docker-desktop/
Docker debe estar en ejecución para poder levantar y acceder al proyecto

## Instalación

Clonar el repositorio:

    git clone <repo-url>
    cd mtg-collection-manager

Crear archivo `.env`:

    cp .env.example .env

Levantar el proyecto:

    docker compose up --build

## Acceso

- Aplicación: http://localhost:8080

## Base de datos

Se inicializa automáticamente al levantar Docker:

- `01-schema.sql` → estructura
- `02_seed.sql` → datos base

## Notas

- Backend: PHP + Slim 4
- Base de datos: MySQL
- Configuración mediante `.env`
- Servicio de reconocimiento (Python) incluido en Docker