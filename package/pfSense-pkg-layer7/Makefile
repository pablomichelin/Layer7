# Layer7 para pfSense CE — port com binário layer7d (C).
# Requer árvore completa do repo: src/layer7d/main.c em ../../src/layer7d/

PORTNAME=	pfSense-pkg-layer7
PORTVERSION=	0.0.3
PORTREVISION=	0
CATEGORIES=	sysutils
MASTER_SITES=	# empty
DISTFILES=	# empty
EXTRACT_ONLY=	# empty

MAINTAINER=	community@layer7-pfsense.local
COMMENT=	Layer 7 classification for pfSense CE (daemon C stub)

LICENSE=	BSD2CLAUSE
LICENSE_FILE=	${.CURDIR}/LICENSE

LAYER7D_SRC=	${.CURDIR}/../../src/layer7d/main.c

.if !exists(${LAYER7D_SRC})
.error "layer7d: fonte em falta (${LAYER7D_SRC}). Mantém src/layer7d/main.c relativamente ao port (clone completo do repositório)."
.endif

WRKSRC=		${WRKDIR}

NO_MTREE=	yes

SUB_FILES=	pkg-install pkg-deinstall
SUB_LIST=	PORTNAME=${PORTNAME}

do-extract:
	${MKDIR} ${WRKSRC}

do-build:
	${CC} ${CFLAGS} -Wall -Wextra -O2 -o ${WRKSRC}/layer7d ${LAYER7D_SRC}

do-install:
	${MKDIR} ${STAGEDIR}${PREFIX}/pkg
	${MKDIR} ${STAGEDIR}/etc/inc/priv
	${MKDIR} ${STAGEDIR}${PREFIX}/www/packages/layer7
	${MKDIR} ${STAGEDIR}${PREFIX}/sbin
	${MKDIR} ${STAGEDIR}${PREFIX}/etc/rc.d
	${MKDIR} ${STAGEDIR}${PREFIX}/etc
	${MKDIR} ${STAGEDIR}${DATADIR}
	${INSTALL_PROGRAM} ${WRKSRC}/layer7d ${STAGEDIR}${PREFIX}/sbin
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/etc/layer7.json.sample \
		${STAGEDIR}${PREFIX}/etc
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/pkg/layer7.inc \
		${STAGEDIR}${PREFIX}/pkg
	${INSTALL_DATA} ${FILESDIR}/etc/inc/priv/layer7.priv.inc \
		${STAGEDIR}/etc/inc/priv
	${INSTALL_DATA} ${FILESDIR}${PREFIX}/www/packages/layer7/layer7_status.php \
		${STAGEDIR}${PREFIX}/www/packages/layer7
	${INSTALL_SCRIPT} ${FILESDIR}${PREFIX}/etc/rc.d/layer7d \
		${STAGEDIR}${PREFIX}/etc/rc.d
	${INSTALL_DATA} ${FILESDIR}${DATADIR}/info.xml \
		${STAGEDIR}${DATADIR}
	@${REINPLACE_CMD} -i '' -e "s|%%PKGVERSION%%|${PKGVERSION}|" \
		${STAGEDIR}${PREFIX}/pkg/layer7.xml \
		${STAGEDIR}${DATADIR}/info.xml

.include <bsd.port.mk>
