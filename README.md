# SonarQube Report Generator

The following uses the SonarQube API to build an exportable report based on 
the items that are found within the scan. This works for both hosted solution 
and a local copy.

## Generating Report

### Using the Docker Image

Running the docker image can be as simple as running the following:

```shell
docker -it --rm \
  -v $(pwd):/mnt/reports \
  -e SONARCLOUD_HOST="https://sonarcloud.io" \
  -e SONARCLOUD_USER="user" \
  -e SONARCLOUD_PASS="abc" \
  -e SONARCLOUD_PROJECTS="project_test" \
  devkteam/sonarqube-report:latest
```

#### Environment Variables

### Using PHP Source

Clone the source code

```shell
git clone git@github.com:kanopi/sonarqube-report sonarcloud-report
cd sonarqube-report
```

Run Composer Install

```shell
composer install
```

Create the `.env` file. Use this to modify any environment variables that should
point to your SonarQube instance.

```shell
cp .env.dist .env
```

Run the `run.php` script

```shell
./run.php
```

## Building From Source

To build the image from source.

```shell
make build
```

## Running full process

Included as part of this is the full process of setting up a local instance of SonarQube and running the scanner on the 
current codebase.

```shell
bash <(curl -fsSL https://raw.githubusercontent.com/kanopi/sonarqube-report/main/run.sh) run
```