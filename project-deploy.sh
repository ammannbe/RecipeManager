#!/bin/bash
#
# Deploy project in current directory


#### Load environment & set variables ####

PROJECT_PATH=$(cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd)

cd "${PROJECT_PATH}"
source .env

[[ -n $CMD_GIT ]] || CMD_GIT="$(which git)"
[[ -n $CMD_PHP ]] || CMD_PHP="$(which keyhelp-php84)"
[[ -n $CMD_COMPOSER ]] || CMD_COMPOSER="$CMD_PHP $(which composer)"
[[ -n $CMD_ARTISAN ]] || CMD_ARTISAN="$CMD_PHP artisan"
[[ -n $CMD_NPM ]] || CMD_NPM="$(which npm)"

CURRENT_STEP=0
TOTAL_STEPS=11
FORCE=false
[[ $1 == "--force" ]] && FORCE=true


#### Helper functions ####

function confirm() {
    if [[ $FORCE == true ]]; then
        return 0
    fi

    while true; do
        read -p "Do you want to proceed? (yes/no) " yn
        case $yn in
            [Yy]* ) return 0;;
            [Nn]* ) exit;;
            * ) echo "Please answer with yes (y) or no (n).";;
        esac
    done
}

function check_or_abort() {
    if [[ $1 -ne 0 ]]; then
        echo "Step ${2}/${TOTAL_STEPS} failed"
        echo "Set app online"
        $CMD_ARTISAN up

        echo "Abort script"
        exit 1
    fi
}

function step() {
    CURRENT_STEP=$((CURRENT_STEP + 1))
    echo "[${CURRENT_STEP}/${TOTAL_STEPS}] $1"
}


#### Main script ####

echo "Update project in ${PROJECT_PATH}"

echo "Current branch:"
$CMD_GIT branch

echo
echo "Following steps will be executed:"
echo "   1. Set app offline"
echo "   2. Pull code from '$($CMD_GIT rev-parse --abbrev-ref HEAD)'"
echo "   3. Install composer packages"
echo "   4. Run migrations"
echo "   4. Clear cache"
echo "   5. Install npm packages"
echo "   6. Build npm packages"
echo "   7. Set app online"
echo
[[ -z $CMD_NPM ]] && echo "  NOTE: npm is not available, skipping step 5 & 6 for JS & CSS." && echo
confirm

step "Set app offline..."
$CMD_ARTISAN down
check_or_abort $? $CURRENT_STEP

step "Pull latest code..."
$CMD_GIT pull
check_or_abort $? $CURRENT_STEP

step "Install composer packages..."
$CMD_COMPOSER install --optimize-autoloader --no-interaction --no-dev
check_or_abort $? $CURRENT_STEP

step "Run migrations..."
$CMD_ARTISAN migrate
check_or_abort $? $CURRENT_STEP

step "Clear cache..."
$CMD_ARTISAN cache:clear \
    && $CMD_ARTISAN config:clear \
    && $CMD_ARTISAN route:clear \
    && $CMD_ARTISAN view:clear
check_or_abort $? $CURRENT_STEP

if [[ -n $CMD_NPM ]]; then
    step "Install npm packages..."
    $CMD_NPM install --no-save
    check_or_abort $? $CURRENT_STEP
else
    echo -ne "\e[9m"
    step "Install npm packages... (skipped)"
    echo -ne "\e[0m"
fi

if [[ -n $CMD_NPM ]]; then
    step "Build npm packages..."
    $CMD_NPM run build
    check_or_abort $? $CURRENT_STEP
else
    echo -ne "\e[9m"
    step "Build npm packages... (skipped)"
    echo -ne "\e[0m"
fi

step "Set app online..."
$CMD_ARTISAN up
check_or_abort $? $CURRENT_STEP
