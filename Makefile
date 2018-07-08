##
# Build project dependncies.
#
# Usage:
# make <target>
#
# make help - show a list of available targets.
# make build - build project
#
include .env
-include .env.local

.DEFAULT_GOAL := help
.PHONY: build build-fed build-fed-prod clean clean-full cs db-import docker-cli docker-destroy docker-logs docker-pull docker-restart docker-start docker-stop drush export-db-dump help import-db import-db-dump install-site lint login rebuild rebuild-full site-install test test-behat update-fixtures
.EXPORT_ALL_VARIABLES: ;

## Build project dependencies.
build:
	$(call title,Building project)
	$(call exec,$(MAKE) docker-start)
	$(call exec,composer install -n --ansi --prefer-dist --no-suggest)
	$(call exec,$(MAKE) site-install)
	@echo ''
	$(call title,Build complete)
	@echo ''
	@printf "${GREEN}Site URL              :${RESET} $(URL)\n"
	@printf "${GREEN}Path inside container :${RESET} $(APP)\n"
	@printf "${GREEN}Path to docroot       :${RESET} $(APP)/$(WEBROOT)\n"
	@printf "${GREEN}Mailhog URL           :${RESET} http://mailhog.docker.amazee.io/\n"
	@printf "${GREEN}One-time login        :${RESET} " && docker-compose exec cli drush -r $(APP)/$(WEBROOT) uublk 1 > /dev/null && docker-compose exec cli drush -r $(APP)/$(WEBROOT) -l $(URL) uli

## Remove dependencies.
clean:
	$(call title,Removing dependencies)
	$(call exec,chmod -Rf 777 $(WEBROOT)/sites/default||true)
	$(call exec,git ls-files --directory --other -i --exclude-from=.gitignore $(WEBROOT)|xargs rm -Rf)

## Remove dependencies and Docker images.
clean-full: docker-stop docker-destroy clean

## Start Docker containers.
docker-start:
	$(call title,Starting Docker containers)
	$(call exec,COMPOSE_CONVERT_WINDOWS_PATHS=1 docker-compose up -d $(filter-out $@,$(MAKECMDGOALS)))
	$(call exec,if docker-compose logs |grep "\[Error\]"; then exit 1; fi)
	@docker ps -a --filter name=^/$(COMPOSE_PROJECT_NAME)_

## Stop Docker containers.
docker-stop:
	$(call title,Stopping Docker containers)
	$(call exec,docker-compose stop $(filter-out $@,$(MAKECMDGOALS)))

## Run Drush command.
drush:
	$(call title,Executing Drush command inside CLI container)
	$(call exec,docker-compose exec cli drush -r $(APP)/$(WEBROOT) $(filter-out $@,$(MAKECMDGOALS)))

## Display this help message.
help:
	@echo ''
	@echo 'Usage:'
	@echo '  ${YELLOW}make${RESET} ${GREEN}<target>${RESET}'
	@echo ''
	@echo 'Targets:'
	@awk '/^[a-zA-Z\-0-9][a-zA-Z\-\_0-9]+:/ { \
		helpMessage = match(lastLine, /^## (.*)/); \
		if (helpMessage) { \
			helpCommand = substr($$1, 0, index($$1, ":")-1); \
			helpMessage = substr(lastLine, RSTART + 3, RLENGTH); \
			printf "  ${YELLOW}%-$(HELP_TARGET_WIDTH)s${RESET} ${GREEN}%s${RESET}\n", helpCommand, helpMessage; \
		} \
	} \
	{ lastLine = $$0 }' $(MAKEFILE_LIST)

## Lint code.
lint:
	$(call title,Linting code)
	$(call exec,vendor/bin/parallel-lint --exclude vendor $(PHP_LINT_EXCLUDES) -e $(PHP_LINT_EXTENSIONS) $(PHP_LINT_TARGETS))
	$(call exec,vendor/bin/phpcs)

## Login to the website.
login:
	$(call title,Generating login link for user 1)
	$(call exec,docker-compose exec cli drush -r $(APP)/$(WEBROOT) uublk 1)
	$(call exec,docker-compose exec cli drush -r $(APP)/$(WEBROOT) uli -l $(URL) | xargs open)

update-fixtures:
	$(call title,Updating fixture files for Drupal $(DRUPAL_VERSION))
	$(call exec,rsync -av --delete --no-progress --exclude-from=$(BUILD)/.rsync-exclude $(BUILD)/ $(FIXTURES)/d$(DRUPAL_VERSION)/)

## Re-build project dependencies.
rebuild: clean build

## clean and fully re-build project dependencies.
rebuild-full: clean-full build

# Install site.
site-install:
	$(call title,Installing a site)
	$(call exec,docker-compose exec cli bash -c "chmod -Rf 777 $(APP)/$(BUILD) || true && rm -Rf $(APP)/$(BUILD) || true && mkdir -p $(APP)/$(WEBROOT)")
	$(call exec,docker-compose exec cli cp -Rf $(APP)/$(FIXTURES)/d$(DRUPAL_VERSION)/. $(APP)/$(BUILD)/)
	$(call exec,docker-compose exec cli composer --working-dir=$(APP)/$(BUILD) install --prefer-dist)
	$(call exec,docker-compose exec cli drush -r $(APP)/$(WEBROOT) si standard -y --db-url=mysql://drupal:drupal@$(MYSQL_HOST)/drupal --account-name=admin --account-pass=admin install_configure_form.enable_update_status_module=NULL install_configure_form.enable_update_status_emails=NULL)
	$(call exec,docker-compose exec cli composer --working-dir=$(APP)/$(BUILD) drupal-post-install)

## Run all tests.
test: test-behat

## Run Behat tests.
test-behat:
	$(call title,Running behat tests)
	$(call exec,docker-compose exec cli vendor/bin/behat --format=progress_fail --colors $(BEHAT_PROFILE) --profile=d$(DRUPAL_VERSION) $(filter-out $@,$(MAKECMDGOALS)))

#-------------------------------------------------------------------------------
# VARIABLES.
#-------------------------------------------------------------------------------
COMPOSE_PROJECT_NAME ?= app

APP ?= /app
WEBROOT ?= web
BUILD ?= build
FIXTURES ?= tests/behat/fixtures
URL ?= http://mysite.docker.amazee.io/

DRUPAL_VERSION ?= 7

PHP_LINT_EXTENSIONS ?= php,inc
PHP_LINT_TARGETS ?= ./
PHP_LINT_TARGETS := $(subst $\",,$(PHP_LINT_TARGETS))
PHP_LINT_EXCLUDES ?= --exclude vendor --exclude node_modules
PHP_LINT_EXCLUDES := $(subst $\",,$(PHP_LINT_EXCLUDES))

# Path to a file with additional sanitization commands.
DB_SANITIZE_SQL ?= .dev/sanitize.sql

# Prefix of the Docker images.
DOCKER_IMAGE_PREFIX ?= amazeeio

# Width of the target column in help target.
HELP_TARGET_WIDTH = 20

# Print verbose messages.
VERBOSE ?= 1

# Colors for output text.
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)

#-------------------------------------------------------------------------------
# FUNCTIONS.
#-------------------------------------------------------------------------------

##
# Execute command and display executed command to user.
#
define exec
	@printf "$$ ${YELLOW}${subst ",',${1}}${RESET}\n" && $1
endef

##
# Display the target title to user.
#
define title
	$(if $(VERBOSE),@printf "${GREEN}==> ${1}...${RESET}\n")
endef

# Pass arguments from CLI to commands.
# @see https://stackoverflow.com/a/6273809/1826109
%:
	@:
