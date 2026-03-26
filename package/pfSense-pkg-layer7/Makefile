# Layer7 para pfSense CE — port com binário layer7d (C) + nDPI.
# Fontes: ../../src/layer7d/main.c, config_parse.c, policy.c, enforce.c, capture.c

PORTNAME=	pfSense-pkg-layer7
PORTVERSION=	1.5.2
PORTREVISION=	0
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	contato@systemup.inf.br
COMMENT=	Layer 7 for pfSense CE by Systemup (www.systemup.inf.br)

LICENSE=	EULA
LICENSE_NAME=	Layer7 End-User License Agreement
LICENSE_PERMS=
LICENSE_FILE=	${.CURDIR}/../../LICENSE

LAYER7D_DIR=	${.CURDIR}/../../src/layer7d

LAYER7_COMMON=	${.CURDIR}/../../src/common
.if !exists(${LAYER7D_DIR}/main.c) || !exists(${LAYER7D_DIR}/config_parse.c) || !exists(${LAYER7D_DIR}/policy.c) || !exists(${LAYER7D_DIR}/enforce.c) || !exists(${LAYER7D_DIR}/license.c) || !exists(${LAYER7D_DIR}/blacklist.c) || !exists(${LAYER7D_DIR}/bl_config.c)
.error "layer7d: fontes em falta em ${LAYER7D_DIR}/ (main.c, config_parse.c, policy.c, enforce.c, license.c, blacklist.c, bl_config.c)."
.endif

NDPI_PREFIX=	/usr/local
.if exists(${NDPI_PREFIX}/include/ndpi/ndpi_api.h)
LAYER7_NDPI_CFLAGS=	-I${NDPI_PREFIX}/include/ndpi -DHAVE_NDPI=1
.if exists(${NDPI_PREFIX}/lib/libndpi.a)
LAYER7_NDPI_LDFLAGS=	${NDPI_PREFIX}/lib/libndpi.a -lpcap -lm -lpthread
.else
.error "layer7d: falta ${NDPI_PREFIX}/lib/libndpi.a. Reinstale/compile nDPI com biblioteca estática para gerar pacote autocontido."
.endif
LAYER7_NDPI_SRCS=	${LAYER7D_DIR}/capture.c
.else
LAYER7_NDPI_CFLAGS=	-DHAVE_NDPI=0
LAYER7_NDPI_LDFLAGS=
LAYER7_NDPI_SRCS=
.endif

WRKSRC=		${WRKDIR}

NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	@${ECHO_CMD} '"${PORTVERSION}"' > ${WRKSRC}/version.str
	${CC} ${CFLAGS} -Wall -Wextra -O2 -I${WRKSRC} -I${LAYER7D_DIR} -I${LAYER7_COMMON} \
		${LAYER7_NDPI_CFLAGS} -o ${WRKSRC}/layer7d \
		${LAYER7D_DIR}/main.c ${LAYER7D_DIR}/config_parse.c ${LAYER7D_DIR}/policy.c \
		${LAYER7D_DIR}/enforce.c ${LAYER7D_DIR}/license.c \
		${LAYER7D_DIR}/blacklist.c ${LAYER7D_DIR}/bl_config.c \
		${LAYER7_NDPI_SRCS} ${LAYER7_NDPI_LDFLAGS} /usr/lib/libcrypto.a

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/etc
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/libexec
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_PROGRAM} ${WRKSRC}/layer7d ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7.json.sample \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7-protos.txt.sample \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/layer7.priv.inc \
		${STAGEDIR}/etc/inc/priv/layer7.priv.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_status.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_settings.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_policies.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_groups.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_categories.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_exceptions.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_events.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_test.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_diagnostics.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_blacklists.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_bl_ajax.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_reports.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_reports_export.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/layer7d \
		${STAGEDIR}${PREFIX}/etc/rc.d
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/layer7-pfctl \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/layer7-unbound-anti-doh \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/pf.conf.sample \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/profiles.json \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/layer7/update-blacklists.sh \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/layer7/layer7-stats-collect.sh \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/layer7/layer7-reports-collect.php \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/layer7/layer7-stats-purge.sh \
		${STAGEDIR}${PREFIX}/etc/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7/reports
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7/blacklists
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7/lang
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/lang/en.php \
		${STAGEDIR}${PREFIX}/etc/layer7/lang
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/lang/pt.php \
		${STAGEDIR}${PREFIX}/etc/layer7/lang
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
