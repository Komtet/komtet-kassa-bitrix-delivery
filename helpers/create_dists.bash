export VERSION=$1

START_PATH="$PWD"
PROJECT_DIR="komtet.delivery"
PROJECT_TAR="$PROJECT_DIR.tar.gz"
DIST_MARKET_DIR="dist/market"
DIST_GITHUB_DIR="dist/github"
VERSION_DIR="$VERSION"
VERSION_TAR="$VERSION.tar.gz"


# Colors
COLOR_OFF="\033[0m"
RED="\033[1;31m"
YELLOW="\e[33m"
CYAN="\033[1;36m"
BLINK="\e[5m"
NOBLINK="\e[25m"


echo -e "${CYAN}Запущена сборка обновлений для загрузок${COLOR_OFF}\n"

LAST_TAG=($(git tag | tail -1))
[ -z "$LAST_TAG" ] && { echo -e "${RED}Последний тег не найден${COLOR_OFF}"; exit 1; }
echo -e "${CYAN}Текущая версия проекта: ${YELLOW}${LAST_TAG}${COLOR_OFF}"

PRE_LAST_TAG=($(git tag | tail -2 | head -1))
[ -z "$PRE_LAST_TAG" ] && { echo -e "${RED}Предпоследний тег не найден${COLOR_OFF}"; exit 1; }
echo -e "${CYAN}Предыдущая версия проекта: ${YELLOW}${PRE_LAST_TAG}${COLOR_OFF}"

DIFFS=($(git diff $LAST_TAG $PRE_LAST_TAG --name-only| grep komtet.delivery))
echo -e "\n${CYAN}Обнаружены отличия в файлах: ${COLOR_OFF}"

#=============== Сборка для маркета ======================
[ -d "$DIST_MARKET_DIR/$VERSION_DIR" ] && rm -rf "$DIST_MARKET_DIR/$VERSION_DIR"
[ -f "$DIST_MARKET_DIR/$VERSION_TAR" ] && rm  "$DIST_MARKET_DIR/$VERSION_TAR"
mkdir -p "$DIST_MARKET_DIR"

for element in "${DIFFS[@]}"
do
   DIRNAME=("$DIST_MARKET_DIR/$(dirname ${element})")
   echo -e "${YELLOW} * ${element} ${COLOR_OFF}"
   mkdir -p $DIRNAME && cp -r ${element} $DIRNAME
done

mv "$DIST_MARKET_DIR/$PROJECT_DIR" "$DIST_MARKET_DIR/$VERSION_DIR"

#=============== Сборка для гитхаб =======================
[ -d "$DIST_GITHUB_DIR/$PROJECT_DIR" ] && rm -rf "$DIST_GITHUB_DIR/$PROJECT_DIR"
[ -f "$DIST_GITHUB_DIR/$PROJECT_TAR" ] && rm  "$DIST_GITHUB_DIR/$PROJECT_TAR"
mkdir -p "$DIST_GITHUB_DIR/$PROJECT_DIR"

cp -r $PROJECT_DIR'/.' "$DIST_GITHUB_DIR/$PROJECT_DIR"

#=================== Архивация ==========================
cd $DIST_MARKET_DIR && tar -czf $VERSION_TAR $VERSION_DIR && rm -rf $VERSION_DIR && cd -
cd $DIST_GITHUB_DIR && \
   tar \
     --exclude=$PROJECT_DIR'/lib/komtet-kassa-php-sdk/.*' \
	  --exclude=$PROJECT_DIR'/lib/komtet-kassa-php-sdk/docker_env' \
	  --exclude=$PROJECT_DIR'/lib/komtet-kassa-php-sdk/tests' \
     -czf $PROJECT_TAR $PROJECT_DIR && \
   rm -rf $PROJECT_DIR && cd -
#=========================================================

echo -e "${CYAN}Сборка обновлений для загрузок завершена${COLOR_OFF}\n"
echo -e "${CYAN}Проверьте архивы: ${COLOR_OFF}"
echo -e "${YELLOW}Для маркета ${BLINK}:${NOBLINK} ${DIST_MARKET_DIR}/${VERSION_TAR}${COLOR_OFF}"
echo -e "${YELLOW}Для GitHub ${BLINK}:${NOBLINK} ${DIST_GITHUB_DIR}/${PROJECT_TAR}${COLOR_OFF}"
