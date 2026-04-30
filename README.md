# MTG Collection Manager

Aplicación web para gestionar colecciones de cartas de Magic: The Gathering.

## Requisitos

- Docker
- Docker Compose

## Instalación

1. Clonar el repositorio:

git clone <repo-url>
cd mtg-collection-manager

2. Crear archivo `.env` (copiar desde `.env.example`):

cp .env.example .env

3. Levantar el proyecto:

docker compose up --build

## Acceso

- Aplicación: http://localhost:8080

## Base de datos

La base de datos se inicializa automáticamente al levantar Docker:

- 01-schema.sql → estructura
- 02_seed.sql → datos base

## Notas

- Backend: PHP + Slim 4
- Base de datos: MySQL
- Configuración mediante `.env`
- Servicio de reconocimiento (Python) incluido en Docker