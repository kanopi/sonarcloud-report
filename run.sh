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

if [[ -f .env ]]; then
  . ./.env
fi

# Configurable Variables
PASSWORD=${PASSWORD:-password}
USERNAME=${USERNAME:-admin}
PORT=${PORT:-9000}
HOST=${HOST:-"http://127.0.0.1:9000"}
MAX_TRIES=${MAX_TRIES:-3}
SLEEPTIME=${SLEEPTIME:-90}
PROJECT_DIRECTORY=${PROJECT_DIRECTORY:-$(pwd)}
PROJECT_NAME=${PROJECT_NAME:-$(basename ${PROJECT_DIRECTORY})}
SERVICE_NAME=${SERVICE_NAME:-sonarqube}

SONARQUBE_CLI_REMOTE_HOST=${SONARQUBE_CLI_REMOTE_HOST:-"http://${SERVICE_NAME}:9000"}
SONARQUBE_SERVICE_IMAGE=${SONARQUBE_SERVICE_IMAGE:-"sonarqube:9-community"}
SONARQUBE_REPORT_IMAGE=${SONARQUBE_REPORT_IMAGE:-"devkteam/sonarqube-report:latest"}
SONARQUBE_CLI_IMAGE=${SONARQUBE_CLI_IMAGE:-"devkteam/sonar-scanner-cli:latest"}
CLEANUP=${CLEANUP}

# Not Configurable
PROJECT_KEY=${PROJECT_KEY:-$(echo "${PROJECT_NAME}" | sed "s/[ |-]/_/g" | sed 's/[^a-zA-Z_]//g' | tr '[:upper:]' '[:lower:]')}
OLDPASS=admin
LOG_FILE=${LOG_FILE:-"/tmp/sonarqube.log"}
TRIES=0

# Find Version of Python
if [[ $(which python) ]]; then
  PYTHON=$(which python)
elif [[ $(which python3) ]]; then
  PYTHON=$(which python3)
fi

#### Helper Functions
echo-red ()      { echo -e "${red}$1${NC}"; }
echo-green ()    { echo -e "${green}$1${NC}"; }
echo-green-bg () { echo -e "${green_bg}$1${NC}"; }
echo-yellow ()   { echo -e "${yellow}$1${NC}"; }

echo-colored() {
    local bg_color=$1
    local text=$2
    local text_color=$3
    local output=$4
	echo -e "${bg_color} ${text} ${NC} ${text_color}${output}${NC}";
	shift 4
	for arg in "$@"; do
		echo -e "           $arg"
	done
}

echo-warning() {
    echo-colored "${yellow_bg}" "WARN:  " "${yellow}" "$@"
}

echo-error() {
    echo-colored "${red_bg}" "ERROR: " "${red}" "$@"
}

echo-notice() {
    echo-colored "${lightmagenta_bg}" "NOTICE:" "${lightmagenta}" "$@"
}

# print string in $1 for $2 times
echo-repeat() {
    seq  -f $1 -s '' $2; echo
}

# prints message to stderr
echo-stderr() {
	(>&2 echo "$@")
}

# Exits fin if previous command exited with non-zero code
if_failed() {
	if [ ! $? -eq 0 ]; then
		echo-red "$*"
		exit 1
	fi
}

# Like if_failed but with more strict error
if_failed_error() {
	if [ ! $? -eq 0 ]; then
		echo-error "$@"
		exit 1
	fi
}

# Parse Options
while getopts "cd:h:l:m:p:r:s:u:-:" OPT; do
  # support long options: https://stackoverflow.com/a/28466267/519360
  if [ "$OPT" = "-" ]; then   # long option: reformulate OPT and OPTARG
    OPT="${OPTARG%%=*}"       # extract long option name
    OPTARG="${OPTARG#$OPT}"   # extract long option argument (may be empty)
    OPTARG="${OPTARG#=}"      # if long option argument, remove assigning `=`
  fi
  case $OPT in
    c|cleanup)            CLEANUP="1";;
    d|directory)          PROJECT_DIRECTORY="${OPTARG}";;
    h|host)               HOST="${OPTARG}";;
    k|project-key)        PROJECT_KEY=$(echo "${OPTARG}" | sed "s/[ |-]/_/g" | sed 's/[^a-zA-Z_]//g' | tr '[:upper:]' '[:lower:]');;
    l|log)                LOG_FILE="${OPTARG}";;
    m|max-tries)          MAX_TRIES="${OPTARG}";;
    p|password)           PASSWORD="${OPTARG}";;
    r|project)            PROJECT_NAME="${OPTARG}";;
    s|cli-remote-host)    SONARQUBE_CLI_REMOTE_HOST="${OPTARG}";;
    u|user)               USERNAME="${OPTARG}";;
    ??* )                 echo-error "Illegal option --$OPT"; exit 2 ;;  # bad long option
    ? )                   exit 2 ;;  # bad short option (error reported via getopts)
  esac
done
shift $((OPTIND-1)) # remove parsed options and args from $@ list

cleanup() {
    echo-warning "Removing services..."
    docker rm -f ${SERVICE_NAME} > /dev/null
}

start_sonarqube() {
    docker run -itd --rm \
        --name ${SERVICE_NAME} \
        -p ${PORT}:${PORT} \
        ${SONARQUBE_SERVICE_IMAGE} > /dev/null
}

sonarqube_running() {
    # shellcheck disable=SC2005
    echo "$(docker ps -a | grep "${SERVICE_NAME}" | wc -l | tr -d '[:blank:]')"
}

check_sonarqube_status() {
    # shellcheck disable=SC2005
    echo "$(curl -fsSL -u "${USERNAME}:${OLDPASS}" "${HOST}/api/system/status" | grep "UP" | wc -l | tr -d '[:blank:]')"
}

get_sonarqube_status() {
    echo $([[ "${1}" == 0 ]] && echo "Not Started" || echo "Started")
}

#### Execution

project_exists() {
    echo $(curl -fsSL -u "${USERNAME}:${PASSWORD}" "${HOST}/api/projects/search?projects=${1}" | ${PYTHON} -c 'import json,sys;obj=json.load(sys.stdin);print(obj["paging"]["total"])')
}

pull_latest_images() {
    echo-notice "Pulling latest version of images..."

    docker pull --quiet ${SONARQUBE_SERVICE_IMAGE} > /dev/null || true
    docker pull --quiet ${SONARQUBE_REPORT_IMAGE} > /dev/null || true
    docker pull --quiet ${SONARQUBE_CLI_IMAGE} > /dev/null || true
}

check_status() {
    echo-notice "Checking SonarQube Status..."

    IS_STARTED=$(sonarqube_running)

    if [[ "${IS_STARTED}" == "1" ]]; then
        echo-warning "SonarQube service already started. Remove other instance. (Y/N)?"
        read remove_service
        remove_service=$(echo "${remove_service}" | tr '[:lower:]' '[:upper:]' | tr -d '[:blank:]')
        if [[ "${remove_service}" == "Y" ]] || [[ "${remove_service}" == "YES" ]]; then
            cleanup
        else
            if_failed_error "Sonarcloud instance already started. Close other one before moving forward."
        fi
    fi
}

start_service() {
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

change_password() {
    echo-notice "Changing initial default password..."

    curl -fsSL -u ${USERNAME}:${OLDPASS} \
        ${HOST}/api/users/change_password \
        -d "login=${USERNAME}" \
        -d "password=${PASSWORD}" \
        -d "previousPassword=${OLDPASS}" >/dev/null
}

update_libraries() {
    echo-notice "Adding extensions to PHP Library..."

    curl -fsSL -u ${USERNAME}:${PASSWORD} \
        ${HOST}/api/settings/set \
        -d 'key=sonar.php.file.suffixes' \
        -d 'values=php&values=php3&values=php4&values=php5&values=phtml&values=inc&values=module' > /dev/null
}

delete_project() {
    echo-notice "Deleting Project: ${1}..."
    curl -fsSL -u ${USERNAME}:${PASSWORD} \
        ${HOST}/api/projects/delete \
        -d "project=${1}"
}

create_project() {
    PROJECT_EXISTS=$(project_exists ${PROJECT_KEY})
    if [[ "${PROJECT_EXISTS}" != "0" ]]; then
        delete_project ${PROJECT_KEY}
    fi

    echo-notice "Creating Project..."

    curl -fsSL -u ${USERNAME}:${PASSWORD} \
        ${HOST}/api/projects/create \
        -d "name=${PROJECT_NAME}" \
        -d "project=${PROJECT_KEY}" > /dev/null
}

run_scanner() {
    echo-notice "Running Scanner..."

    docker run --rm -it -v ${PROJECT_DIRECTORY}:/usr/src \
        --link ${SERVICE_NAME} \
        ${SONARQUBE_CLI_IMAGE} \
        sonar-scanner \
        -Dsonar.projectKey=${PROJECT_KEY} \
        -Dsonar.sources=. \
        -Dsonar.host.url=${SONARQUBE_CLI_REMOTE_HOST} \
        -Dsonar.login="${USERNAME}" \
        -Dsonar.password="${PASSWORD}" > ${LOG_FILE}
}

run_report() {
    echo-notice "Generating Report..."

    docker run --rm -it -v ${PROJECT_DIRECTORY}:/mnt/reports \
        --link ${SERVICE_NAME} \
        -e SONARQUBE_HOST=${SONARQUBE_CLI_REMOTE_HOST} \
        -e SONARQUBE_USER="${USERNAME}" \
        -e SONARQUBE_PASS="${PASSWORD}" \
        -e SONARQUBE_PROJECTS="${PROJECT_KEY}" \
        ${SONARQUBE_REPORT_IMAGE}
}

check_requirements() {
  $(which docker > /dev/null) || if_failed_error "Docker Binary not found"
}

usage() {
  echo "\

Run SonarQube reporting for the code.

Usage:
  $0 <options> <command>

Options:
  -c, --cleanup            Cleanup all the items after done running report.

  -d, --directory          Directory to run the report in.
                           (Default: ${PROJECT_DIRECTORY})

  -h, --host               Hostname to access SonarQube data.
                           (Default: ${HOST})

  -k, --project-key        Project Key
                           (Default: ${PROJECT_KEY})

  -m, --max-tries          Max number of tries to check for remote host.
                           (Default: ${MAX_TRIES})

  -p, --password           Set the password for connecting to the instance of SonarQube/SonarCloud
                           (Default: ${PASSWORD})

  -r, --project            Set the project name
                           (Default: ${PROJECT_NAME})

  -s, --cli-remote-host    Set the remote host to connect to for the cli
                           (Default: ${SONARQUBE_CLI_REMOTE_HOST})

  -t, --port               Port to access SonarQube endpoint.
                           (Default: ${PORT})

Commands:
  cleanup              Cleanup and remove all standing Docker containers.

  run-report           Generate a report based on the latest scan from SonarQube.

  run-scanner          Run the SonarQube CLI Scanner on the current code base. This is
                       run in an isolated Docker container.

  new-project          Create a new project, run the scanner, and generate the report necessary
                       for the provided instance.

  run                  Start the process from the beginning
                       - Check if SonarQube Instance is running
                       - Pull the latest version of Docker Images
                       - Start SonarQube Service.
                       - Change the password for first setup
                       - Update the libraries for the instance
                       - Create project on the SonarQube instance
                       - Run the CLI scanner
                       - Generate report

"
}

check_requirements

# Execute subcommands
case "$1" in
    # Individual Commands
    run-scanner)
        run_scanner
        ;;
    run-report)
        run_report
        ;;
    cleanup)
        cleanup
        ;;
    # Compound Commands
    new-project)
        create_project
        run_scanner
        run_report
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
    start)
        check_status
        pull_latest_images
        start_service
        ;;
    run)
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
    help)
        usage
        ;;
    *)
        usage
        echo-error "Command: ${1} not supported"
        exit 1
        ;;
esac
