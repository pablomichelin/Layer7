# Layer7 para pfSense CE — port com binário layer7d (C) + nDPI.
# Fontes: ../../src/layer7d/main.c, config_parse.c, policy.c, enforce.c, capture.c

PORTNAME=	pfSense-pkg-layer7
PORTVERSION=	1.9.40
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

LIB_DEPENDS=	libldap.so.2:net/openldap26-client

LAYER7D_DIR=	${.CURDIR}/../../src/layer7d
LAYER7_TLSPROXY_DIR=	${.CURDIR}/../../src/layer7-tlsproxy
# GO produto + 20.10b: helper no .pkg; default OFF; listen/rdr só com mitm_effective.
WITH_LAYER7_TLSPROXY?=	yes

LAYER7_COMMON=	${.CURDIR}/../../src/common
.if !exists(${LAYER7D_DIR}/main.c) || !exists(${LAYER7D_DIR}/config_parse.c) || !exists(${LAYER7D_DIR}/policy.c) || !exists(${LAYER7D_DIR}/enforce.c) || !exists(${LAYER7D_DIR}/license.c) || !exists(${LAYER7D_DIR}/features.c) || !exists(${LAYER7D_DIR}/blacklist.c) || !exists(${LAYER7D_DIR}/bl_config.c) || !exists(${LAYER7D_DIR}/allowlist.c) || !exists(${LAYER7D_DIR}/log_store.c) || !exists(${LAYER7D_DIR}/identity_map.c) || !exists(${LAYER7D_DIR}/identity_ldap.c) || !exists(${LAYER7D_DIR}/identity_radius.c) || !exists(${LAYER7D_DIR}/identity_dc.c)
.error "layer7d: fontes em falta em ${LAYER7D_DIR}/ (main.c … identity_dc.c)."
.endif
.if ${WITH_LAYER7_TLSPROXY:tl} == yes
.if !exists(${LAYER7_TLSPROXY_DIR}/main.c) || !exists(${LAYER7_TLSPROXY_DIR}/ipc.c) || !exists(${LAYER7_TLSPROXY_DIR}/tls_lab.c)
.error "layer7-tlsproxy: fontes em falta em ${LAYER7_TLSPROXY_DIR}/ (GO produto / 20.10a)."
.endif
.endif
NDPI_PREFIX=	/usr/local
.if exists(${NDPI_PREFIX}/include/ndpi/ndpi_api.h)
LAYER7_NDPI_CFLAGS=	-I${NDPI_PREFIX}/include/ndpi -DHAVE_NDPI=1
.if exists(${NDPI_PREFIX}/lib/libndpi.a)
LAYER7_NDPI_LDFLAGS=	${NDPI_PREFIX}/lib/libndpi.a -lpcap -lm
.else
.error "layer7d: falta ${NDPI_PREFIX}/lib/libndpi.a. Reinstale/compile nDPI com biblioteca estática para gerar pacote autocontido."
.endif
LAYER7_NDPI_SRCS=	${LAYER7D_DIR}/capture.c
.else
LAYER7_NDPI_CFLAGS=	-DHAVE_NDPI=0
LAYER7_NDPI_LDFLAGS=
LAYER7_NDPI_SRCS=
.endif
# identity_map + ldap + radius + dc (20.12 / 20.17 / 20.19 / 20.20 / ADR-0028)
LAYER7_PTHREAD_LDFLAGS=	-lpthread
LAYER7_LDAP_CFLAGS=	-DHAVE_OPENLDAP=1 -I/usr/local/include
LAYER7_LDAP_LDFLAGS=	-L/usr/local/lib -lldap -llber

WRKSRC=		${WRKDIR}

NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	@${ECHO_CMD} '"${PKGVERSION}"' > ${WRKSRC}/version.str
	${CC} ${CFLAGS} -Wall -Wextra -O2 -I${WRKSRC} -I${LAYER7D_DIR} -I${LAYER7_COMMON} \
		${LAYER7_NDPI_CFLAGS} ${LAYER7_LDAP_CFLAGS} -o ${WRKSRC}/layer7d \
		${LAYER7D_DIR}/main.c ${LAYER7D_DIR}/config_parse.c ${LAYER7D_DIR}/policy.c \
		${LAYER7D_DIR}/enforce.c ${LAYER7D_DIR}/license.c \
		${LAYER7D_DIR}/features.c \
		${LAYER7D_DIR}/blacklist.c ${LAYER7D_DIR}/bl_config.c \
		${LAYER7D_DIR}/allowlist.c ${LAYER7D_DIR}/log_store.c \
		${LAYER7D_DIR}/identity_map.c ${LAYER7D_DIR}/identity_ldap.c \
		${LAYER7D_DIR}/identity_radius.c ${LAYER7D_DIR}/identity_dc.c \
		${LAYER7_NDPI_SRCS} ${LAYER7_NDPI_LDFLAGS} ${LAYER7_PTHREAD_LDFLAGS} \
		${LAYER7_LDAP_LDFLAGS} \
		/usr/lib/libssl.a /usr/lib/libcrypto.a
.if ${WITH_LAYER7_TLSPROXY:tl} == yes
	${CC} ${CFLAGS} -Wall -Wextra -O2 -I${LAYER7_TLSPROXY_DIR} \
		-DLAYER7_TLSPROXY_VERSION=\"0.1.1\" \
		-o ${WRKSRC}/layer7-tlsproxy \
		${LAYER7_TLSPROXY_DIR}/main.c ${LAYER7_TLSPROXY_DIR}/ipc.c \
		${LAYER7_TLSPROXY_DIR}/tls_lab.c \
		/usr/lib/libssl.a /usr/lib/libcrypto.a -lpthread
.endif

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
.if ${WITH_LAYER7_TLSPROXY:tl} == yes
	${INSTALL_PROGRAM} ${WRKSRC}/layer7-tlsproxy ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/layer7-tlsproxy \
		${STAGEDIR}${PREFIX}/etc/rc.d
.endif
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7.json.sample \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7-protos.txt.sample \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7-fa47-icons.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/layer7.priv.inc \
		${STAGEDIR}/etc/inc/priv/layer7.priv.inc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_status.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_settings.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_policies.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_remote_access.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_identity.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_mitm.php \
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
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_settings_ajax.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_settings_update.js \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/layer7/js
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/js/chart.umd.min.js \
		${STAGEDIR}${PREFIX}/www/packages/layer7/js
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_allowlist.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_devices.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_reports.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_reports_export.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_removal.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/layer7d \
		${STAGEDIR}${PREFIX}/etc/rc.d
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/layer7-pfctl \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/layer7-unbound-anti-doh \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/libexec/layer7-blockpage \
		${STAGEDIR}${PREFIX}/libexec
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/layer7-blockpage \
		${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/www/layer7-blockpage
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/layer7-blockpage/router.php \
		${STAGEDIR}${PREFIX}/www/layer7-blockpage
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/layer7-blockpage/index.php \
		${STAGEDIR}${PREFIX}/www/layer7-blockpage
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/pf.conf.sample \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/profiles.json \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/remote-access-catalog.json \
		${STAGEDIR}${PREFIX}/etc/layer7
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/allowlist-seed.txt \
		${STAGEDIR}${PREFIX}/etc/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/layer7/blacklists
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7/blacklists/config.json.sample \
		${STAGEDIR}${PREFIX}/etc/layer7/blacklists
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
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/blacklists-signing-public-key.pem \
		${STAGEDIR}${DATADIR}
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
