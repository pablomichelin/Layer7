/*
 * capture.h — Captura de pacotes via pcap + classificação nDPI.
 *
 * O módulo abre uma interface via pcap (BPF no FreeBSD), alimenta
 * cada pacote ao nDPI e, quando um fluxo é classificado, invoca o
 * callback registado com (src_ip, app_name, category_name).
 *
 * Thread-safety: NÃO é thread-safe. Chamar apenas do main loop.
 */
#ifndef LAYER7_CAPTURE_H
#define LAYER7_CAPTURE_H

#include <stdint.h>

/*
 * Callback invocado quando nDPI classifica um fluxo.
 *   iface:    nome da interface de captura (e.g. "em0")
 *   src_ip:   IPv4 dotted-quad da origem escolhida para logging
 *   dst_ip:   IPv4 dotted-quad do destino escolhido para logging
 *   app:      nome do protocolo detectado (e.g. "BitTorrent")
 *   category: categoria nDPI (e.g. "Download-FileTransfer-FileSharing")
 *   host:     hint opcional de hostname obtido por correlação DNS (ou NULL)
 */
typedef void (*layer7_flow_cb)(const char *iface, const char *src_ip,
    const char *dst_ip, const char *app, const char *category,
    const char *host);

/*
 * Callback invocado quando uma resposta DNS (RR tipo A) e observada.
 *   domain:      nome do dominio resolvido (e.g. "youtube.com")
 *   resolved_ip: IPv4 dotted-quad do IP resolvido
 *   ttl:         TTL do record DNS (em segundos)
 */
typedef void (*layer7_dns_cb)(const char *domain, const char *resolved_ip,
    uint32_t ttl);

struct layer7_capture;

/*
 * Inicializa nDPI e abre a interface para captura.
 * ifname:      nome da interface (e.g. "em0", "igb1")
 * snaplen:     bytes por pacote (1536 recomendado)
 * cb:          callback de fluxo classificado
 * dns_cb:      callback de DNS observado (pode ser NULL)
 * protos_file: caminho para custom protocols (NULL = default /usr/local/etc/layer7-protos.txt)
 * Retorno:     handle opaco ou NULL em erro (errmsg em errbuf se != NULL).
 */
struct layer7_capture *layer7_capture_open(const char *ifname, int snaplen,
    layer7_flow_cb cb, layer7_dns_cb dns_cb, const char *protos_file,
    char *errbuf, int errbuflen);

/*
 * Processa até batch_size pacotes (non-blocking se timeout_ms <= 0).
 * Retorno: número de pacotes processados, ou -1 em erro.
 */
int layer7_capture_poll(struct layer7_capture *cap, int batch_size);

/*
 * Estatísticas de captura.
 */
void layer7_capture_stats(const struct layer7_capture *cap,
    unsigned long long *pkts_total, unsigned long long *flows_active,
    unsigned long long *flows_classified, unsigned long long *flows_expired);

/*
 * Libera recursos (pcap_close + ndpi_exit).
 */
void layer7_capture_close(struct layer7_capture *cap);

#endif /* LAYER7_CAPTURE_H */
