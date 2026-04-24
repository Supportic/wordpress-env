.SILENT:
.ONESHELL:
SHELL = /bin/bash
MAKEFLAGS += --no-print-directory

WORDPRESS_BASH_EXEC = docker compose exec --user www-data wordpress bash
WORDPRESS_BASH_RUN = docker compose run --rm --user www-data --entrypoint bash wordpress
WPCLI_BASH_RUN = docker compose run --rm wpcli bash
WP_CLI_RUN = docker compose run --rm wpcli wp

shell:
	$(WORDPRESS_BASH_EXEC)
wpcli:
	$(WPCLI_BASH_RUN)

###############################
# docker
###############################

start:
	docker compose up -d wordpress db adminer mailpit
stop:
	docker compose stop
down:
	docker compose down
restart: down start
remove:
	$(MAKE) delete-wordpress
	docker compose down -v
erase:
	$(MAKE) delete-wordpress
	docker compose down -v --rmi all

install: install-precondition install-images start install-wordpress
reinstall: remove install
# reset WP to default installation
reset:
	$(WP_CLI_RUN) db reset --defaults --yes
	$(MAKE) install-wordpress

backup:
	$(WP_CLI_RUN) db export wp-content/backup.sql --add-drop-table
	zip -rq backup.zip wordpress/wp-content
import-backup:
	unzip -oq backup.zip
	$(WP_CLI_RUN) db import wp-content/backup.sql
	rm -rf wordpress/wp-content/backup.sql
	$(WP_CLI_RUN) core update-db
	$(MAKE) symlink-docker-create

install-precondition:
	@if [ ! -f .env ]; then\
		echo "Copy and adjust values .env.sample => .env";\
		exit 1;\
	fi

rebuild: install-images restart

install-images:
	docker compose build --pull wordpress wpcli
	docker compose pull db adminer mailpit
	@docker rmi $$(docker images -q -f "dangling=true" -f "label=autodelete=true") > /dev/null 2>&1 || true

install-wordpress:
	@echo "Installing WordPress..."
	$(WPCLI_BASH_RUN) -c "setup-wordpress"
	$(MAKE) symlink-docker-create
	@echo "WordPress installation complete."
delete-wordpress:
	$(MAKE) symlink-docker-remove
	@echo "Deleting WordPress files..."
	@find wordpress -maxdepth 1 -mindepth 1 ! -name '.gitkeep' -exec rm -rf {} +
	@echo "WordPress files deleted."

log:
	tail -f -n 30 wordpress/wp-content/debug.log 2> /dev/null
clean-log:
	echo "" > wordpress/wp-content/debug.log;
cc:
	$(WP_CLI_RUN) cache flush

symlink-docker-create:
	$(WORDPRESS_BASH_RUN) -c 'symlink create'
symlink-docker-remove:
	$(WORDPRESS_BASH_RUN) -c 'symlink remove'
symlink-docker-remove-broken:
	$(WORDPRESS_BASH_RUN) -c 'symlink remove-broken'
symlink-docker-recreate:
	$(WORDPRESS_BASH_RUN) -c 'symlink recreate'
