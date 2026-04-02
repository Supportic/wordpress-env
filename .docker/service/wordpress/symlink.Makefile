####################################################################
# This file got converted and immproved into a script -> symlink.sh
####################################################################

.SILENT:
.ONESHELL:
SHELL = /bin/bash
MAKEFLAGS += --no-print-directory

DEV_DIR := wp-dev
WORDPRESS_DIR := /var/www/html
WP_CONTENT_DIR := $(WORDPRESS_DIR)/wp-content

symlink-docker-create: symlink-docker-create-user
symlink-docker-remove: symlink-docker-remove-user symlink-docker-remove-broken
symlink-docker-recreate: symlink-docker-remove symlink-docker-create

symlink-docker-create-user:
	SYNC_DIRS=$$(find $(DEV_DIR) -maxdepth 2 -mindepth 2 -type d)
	SYNC_FILES=$$(find $(DEV_DIR)/{mu-plugins,plugins} -maxdepth 1 -type f)
	COMBINED_LIST=$$(echo $$SYNC_DIRS $$SYNC_FILES);
	@echo "Symlinking files and directories of user from $(DEV_DIR) to $(WP_CONTENT_DIR)"
	@for SRC_ITEM_PATH in $$COMBINED_LIST; do \
		WP_DIR_TYPE=$$(echo $$SRC_ITEM_PATH | cut -d'/' -f2); \
		SRC_ITEM_NAME=$$(echo $$SRC_ITEM_PATH | cut -d'/' -f3); \
		if [ ! -d "$(WP_CONTENT_DIR)/$$WP_DIR_TYPE" ]; then \
			echo "  Directory $(WP_CONTENT_DIR)/$$WP_DIR_TYPE does not exist, creating $$WP_DIR_TYPE."; \
			mkdir -p "$(WP_CONTENT_DIR)/$$WP_DIR_TYPE"; \
		fi; \
		if [ -L "$(WP_CONTENT_DIR)/$$WP_DIR_TYPE/$$SRC_ITEM_NAME" ]; then \
			echo "  Symlink $(WP_CONTENT_DIR)/$$WP_DIR_TYPE/$$SRC_ITEM_NAME already exists, force recreating"; \
			ln -sfn --relative "$$SRC_ITEM_PATH" "$(WP_CONTENT_DIR)/$$WP_DIR_TYPE"; \
      continue; \
		fi; \

		echo "  Symlinking $$SRC_ITEM_PATH to $(WP_CONTENT_DIR)/$$WP_DIR_TYPE/$$SRC_ITEM_NAME"; \
		ln -sfn --relative "$$SRC_ITEM_PATH" "$(WP_CONTENT_DIR)/$$WP_DIR_TYPE"; \
	done
	@echo "Symlinking complete."

symlink-docker-remove-user:
	SYNC_DIRS=$$(find $(DEV_DIR) -maxdepth 2 -mindepth 2 -type d)
	SYNC_FILES=$$(find $(DEV_DIR)/{mu-plugins,plugins} -maxdepth 1 -type f)
	COMBINED_LIST=$$(echo $$SYNC_DIRS $$SYNC_FILES);
	@echo "Removing symlinks from user in $(WP_CONTENT_DIR)"
	@for SRC_ITEM_PATH in $$COMBINED_LIST; do \
		WP_DIR_TYPE=$$(echo $$SRC_ITEM_PATH | cut -d'/' -f2); \
		SRC_ITEM_NAME=$$(echo $$SRC_ITEM_PATH | cut -d'/' -f3); \
		TARGET_PATH="$(WP_CONTENT_DIR)/$$WP_DIR_TYPE/$$SRC_ITEM_NAME"; \
		if [ ! -L "$$TARGET_PATH" ]; then \
			echo "  Symlink $$TARGET_PATH does not exist, skipping"; \
			continue; \
		fi; \

		echo "  Removing symlink $$TARGET_PATH"; \
		unlink "$$TARGET_PATH"; \
	done
	@echo "Removing symlinks complete."

symlink-docker-remove-broken:
	BROKEN_LINKS=$$(find $(WP_CONTENT_DIR) -xtype l -exec ls -l {} + | awk '{print $$NF}')
	@echo "Removing broken symlinks in $(WP_CONTENT_DIR)"
	@for BROKEN_LINK in $$BROKEN_LINKS; do \
		#  skip symlinks which were not created from us locally
		if case $$BROKEN_LINK in "/var/www/html"* ) true;; *) false;; esac; then \
				continue; \
		fi; \
		TRIMMED_LINK=$$(echo "$$BROKEN_LINK" | sed 's|.*$(DEV_DIR)/||'); \
		echo "  Removing broken symlink $(WP_CONTENT_DIR)/$$TRIMMED_LINK"; \
		unlink "$(WP_CONTENT_DIR)/$$TRIMMED_LINK"; \
	done
	@echo "Removing broken symlinks complete."
