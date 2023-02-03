IMAGE ?= devkteam/sonarqube-report
BUILD_IMAGE_TAG ?= $(IMAGE):build
NAME = sonarqube-report
CWD = $(shell pwd)

.EXPORT_ALL_VARIABLES:

.PHONY: build stop logs logs-follow clean shell test test-run test-build

default: build

build:
	docker build -t $(BUILD_IMAGE_TAG) .

test-build: build test

test:
	mkdir reports >/dev/null 2>&1 || true
	docker run -it --rm -v $(shell pwd)/reports:/mnt/reports --env-file .env $(BUILD_IMAGE_TAG)

test-run:
	mkdir reports >/dev/null 2>&1 || true
	docker run -it --rm -v $(shell pwd)/reports:/mnt/reports --env-file .env $(BUILD_IMAGE_TAG) sh

shell:
	docker exec -u docker -it $(NAME) bash -il

stop:
	docker stop $(NAME)

logs:
	docker logs $(NAME)

logs-follow:
	docker logs -f $(NAME)

clean:
	docker rm -vf $(NAME) >/dev/null 2>&1 || true

push:
	docker tag $(BUILD_IMAGE_TAG) devkteam/sonarqube-report:latest
	docker push devkteam/sonarqube-report:latest

build-push: build push