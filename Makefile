# Atajos para gestionar el entorno Docker de Portal WORTEV.
# En Windows requiere tener "make" (opcional). Los mismos comandos
# están documentados en el README para usar sin make.

.PHONY: build up down restart logs shell artisan composer npm mysql redis

build:        ## Construye las imágenes
	docker compose build

up:           ## Levanta los contenedores en segundo plano
	docker compose up -d

down:         ## Detiene y elimina los contenedores
	docker compose down

restart:      ## Reinicia los contenedores
	docker compose restart

logs:         ## Muestra los logs en vivo
	docker compose logs -f

shell:        ## Abre una shell en el contenedor app
	docker compose exec app bash

artisan:      ## Ejecuta artisan (uso: make artisan cmd="migrate")
	docker compose exec app php artisan $(cmd)

composer:     ## Ejecuta composer (uso: make composer cmd="install")
	docker compose exec app composer $(cmd)

npm:          ## Ejecuta npm (uso: make npm cmd="install")
	docker compose exec app npm $(cmd)

mysql:        ## Abre el cliente de MySQL
	docker compose exec mysql mysql -u$(DB_USERNAME) -p

redis:        ## Abre el cliente de Redis
	docker compose exec redis redis-cli
