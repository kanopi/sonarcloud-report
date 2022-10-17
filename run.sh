#!/usr/bin/env bash

set -e

if [[ "${DEBUG}" != "" ]]; then
    set -ex
fi

#### VARIABLES
# Console colors
green='\033[0;32;49m'
green_bg='\033[0;42;30m'
yellow='\033[0;33;49m'
yellow_bold='\033[1;33;49m'
yellow_bg='\033[0;43;30m'
red='\033[0;91;49m'
red_bg='\033[0;101;30m'
blue='\033[0;34;49m'
lime='\033[0;92;49m'
acqua='\033[0;96;49m'
magenta='\033[0;35;49m'
lightmagenta='\033[0;95;49m'
lightmagenta_bg='\033[0;105;30m'
NC='\033[0m'

# Configurable
PASS=${PASSWORD:-password}
PROJECT_NAME=${PROJECT_NAME:-"Test Project"}
PORT=${PORT:-"9000"}
HOST=${HOST:-"http://127.0.0.1:${PORT}"}
MAX_TRIES=${MAX_TRIES:-3}
SLEEPTIME=${SLEEPTIME:-90}
PROJECT_DIRECTORY=${PROJECT_DIRECTORY:-$(pwd)}
IMAGE_TAG=${IMAGE_TAG:-"8.9-community"}
SERVICE_NAME=${SERVICE_NAME:-sonarqube}

SONARQUBE_CLI_REMOTE_HOST=${SONARQUBE_CLI_REMOTE_HOST:-"http://sonarqube:${PORT}"}

SONARQUBE_SERVICE_IMAGE="sonarqube:${IMAGE_TAG}"
SONARQUBE_REPORT_IMAGE="devkteam/sonarqube-report:latest"
SONARQUBE_CLI_IMAGE="sonarsource/sonar-scanner-cli:latest"

CLEANUP=${CLEANUP}

# Not Configurable
PROJECT_KEY=$(echo "${PROJECT_NAME}" | sed "s/[ |-]/_/g" | sed 's/[^a-zA-Z_]//g' | tr '[:upper:]' '[:lower:]')
USER=admin
OLDPASS=admin
TRIES=0
LOG_FILE="/tmp/${PROJECT_KEY}.sonarqube.log"


#### Helper Functions

echo-red ()      { echo -e "${red}$1${NC}"; }
echo-green ()    { echo -e "${green}$1${NC}"; }
echo-green-bg () { echo -e "${green_bg}$1${NC}"; }
echo-yellow ()   { echo -e "${yellow}$1${NC}"; }

echo-warning ()
{
	echo -e "${yellow_bg} WARNING: ${NC} ${yellow}$1${NC}";
	shift
	for arg in "$@"; do
		echo -e "           $arg"
	done
}

echo-error ()
{
	echo -e "${red_bg} ERROR: ${NC} ${red}$1${NC}"
	shift
	for arg in "$@"; do
		echo -e "         $arg"
	done
}

echo-notice ()
{
	echo -e "${lightmagenta_bg} NOTICE: ${NC} ${lightmagenta}$1${NC}"
	shift
	for arg in "$@"; do
		echo -e "         $arg"
	done
}

# print string in $1 for $2 times
echo-repeat ()
{
    seq  -f $1 -s '' $2; echo
}

# prints message to stderr
echo-stderr ()
{
	(>&2 echo "$@")
}

# Exits fin if previous command exited with non-zero code
if_failed ()
{
	if [ ! $? -eq 0 ]; then
		echo-red "$*"
		exit 1
	fi
}

# Like if_failed but with more strict error
if_failed_error ()
{
	if [ ! $? -eq 0 ]; then
		echo-error "$@"
		exit 1
	fi
}

cleanup () {
    echo-warning "Removing services..."
    docker rm -f ${SERVICE_NAME} > /dev/null
}

start_sonarqube()
{
    docker run -itd --rm \
        --name ${SERVICE_NAME} \
        -p ${PORT}:${PORT} \
        sonarqube:${IMAGE_TAG} > /dev/null
}

sonarqube_running ()
{
    echo $(docker ps -a | grep ${SERVICE_NAME} | wc -l | tr -d '[:blank:]')
}

check_sonarqube_status ()
{
    echo $(curl -fsSL -u ${USER}:${OLDPASS} \
        ${HOST}/api/system/status | grep "UP" | wc -l | tr -d '[:blank:]')
}

get_sonarqube_status ()
{
    echo $([[ "${1}" == 0 ]] && echo "Not Started" || echo "Started")
}

#### Execution

project_exists()
{
    echo $(curl -fsSL -u ${USER}:${PASS} \
            ${HOST}/api/projects/search?projects=${1}
            | jq -r '.components | length' )
}

pull_latest_images()
{
    echo-notice "Pulling latest version of images..."

    docker pull --quiet ${SONARQUBE_SERVICE_IMAGE} > /dev/null
    docker pull --quiet ${SONARQUBE_REPORT_IMAGE} > /dev/null
    docker pull --quiet ${SONARQUBE_CLI_IMAGE} > /dev/null
}

check_status()
{
    echo-notice "Checking Sonarqube Status..."

    IS_STARTED=$(sonarqube_running)

    if [[ "${IS_STARTED}" == "1" ]]; then
        echo-warning "Sonarqube service already started. Remove other instance. (Y/N)?"
        read remove_service
        remove_service=$(echo "${remove_service}" | tr '[:lower:]' '[:upper:]' | tr -d '[:blank:]')
        if [[ "${remove_service}" == "Y" ]] || [[ "${remove_service}" == "YES" ]]; then
            cleanup
        else
            if_failed_error "Sonarcloud instance already started. Close other one before moving forward."
        fi
    fi
}

start_service ()
{
    echo-notice "Starting Sonarqube..."

    start_sonarqube

    echo-notice "Sleeping for ${SLEEPTIME} seconds to wait for service to start..."

    sleep ${SLEEPTIME}

    # Divide by half
    SLEEPTIME=$((SLEEPTIME/2))

    echo-notice "Getting service status..."

    # Get status from the service.
    RESPONSE=$(check_sonarqube_status)

    TRIES=$((TRIES + 1))

    echo-notice "Service status...$(get_sonarqube_status $RESPONSE)"

    # Check and see service is responding yet.
    while [[ "${RESPONSE}" != "1" ]] && [[ "${TRIES}" < "${MAX_TRIES}" ]]; do
        echo-notice "Not started yet. Sleeping for ${SLEEPTIME} seconds..."
        sleep ${SLEEPTIME}
        RESPONSE=$(check_sonarqube_status)
        echo-notice "Service status...$(get_sonarqube_status $RESPONSE)"
        TRIES=$((TRIES + 1))
    done

    if [[ "${RESPONSE}" != "1" ]]; then
        if_failed_error "Service not properly starting. Exiting."
    fi

    echo-notice "Service started..."
}

change_password()
{
    echo-notice "Changing initial default password..."

    curl -fsSL -u ${USER}:${OLDPASS} \
        ${HOST}/api/users/change_password \
        -d "login=${USER}" \
        -d "password=${PASS}" \
        -d "previousPassword=${OLDPASS}" >/dev/null
}

update_libraries()
{
    echo-notice "Adding extensions to PHP Library..."

    curl -fsSL -u ${USER}:${PASS} \
        ${HOST}/api/settings/set \
        -d 'key=sonar.php.file.suffixes' \
        -d 'values=php&values=php3&values=php4&values=php5&values=phtml&values=inc&values=module' > /dev/null
}

delete_project()
{
    echo-notice "Deleting Project: ${1}..."
    curl -fsSL -u ${USER}:${PASS} \
        ${HOST}/api/projects/delete \
        -d "project=${1}"
}

create_project()
{
    PROJECT_EXISTS=$(project_exists ${PROJECT_KEY})
    if [[ "${PROJECT_EXISTS}" != "0" ]]; then
        delete_project ${PROJECT_KEY}
    fi

    echo-notice "Creating Project..."

    curl -fsSL -u ${USER}:${PASS} \
        ${HOST}/api/projects/create \
        -d "name=${PROJECT_NAME}" \
        -d "project=${PROJECT_KEY}" > /dev/null
}

run_scanner()
{
    echo-notice "Running Scanner..."

    docker run --rm -it -v ${PROJECT_DIRECTORY}:/usr/src \
        --link ${SERVICE_NAME} \
        ${SONARQUBE_CLI_IMAGE} \
        sonar-scanner \
        -Dsonar.projectKey=${PROJECT_KEY} \
        -Dsonar.sources=. \
        -Dsonar.host.url=${SONARQUBE_REMOTE_HOST} \
        -Dsonar.login="${USER}" \
        -Dsonar.password="${PASS}" > ${LOG_FILE}
}

run_report()
{
    echo-notice "Generating Report..."

    docker run --rm -it -v ${PROJECT_DIRECTORY}:/mnt/reports \
        --link ${SERVICE_NAME} \
        -e SONARQUBE_HOST=${SONARQUBE_CLI_REMOTE_HOST} \
        -e SONARQUBE_USER="${USER}" \
        -e SONARQUBE_PASS="${PASS}" \
        -e SONARQUBE_PROJECTS="${PROJECT_KEY}" \
        ${SONARQUBE_REPORT_IMAGE}
}

check_requirements()
{
  $(which docker > /dev/null) || if_failed_error "Docker Binary not found"
  $(which jq > /dev/null) || if_failed_error "jQ Binary not found"
}

check_requirements

case "$1" in
    pull-latest)
        pull_latest_images
        ;;
    run-scanner)
        run_scanner
        ;;
    run-report)
        run_report
        ;;
    create-project)
        create_project
        ;;
    cleanup)
        cleanup
        ;;
    change-password)
        change_password
        ;;
    run-current)
        update_libraries
        create_project
        run_scanner
        run_report

        if [[ "${CLEANUP}" != "" ]]; then
            cleanup
        fi

        echo-green-bg "Completed"
        ;;
    *)
        check_status
        pull_latest_images
        start_service
        change_password
        update_libraries
        create_project
        run_scanner
        run_report

        if [[ "${CLEANUP}" != "" ]]; then
            cleanup
        fi

        echo-green-bg "Completed"
        ;;
esac