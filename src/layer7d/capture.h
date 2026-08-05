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
 *   src_ip:   endereço origem (IPv4 ou IPv6 textual)
 *   dst_ip:   endereço destino (IPv4 ou IPv6 textual)
 *   app:      nome do protocolo detectado (e.g. "BitTorrent")
 *   category: categoria nDPI (e.g. "Download-FileTransfer-FileSharing")
 *   host:     hint opcional de hostname obtido por correlação DNS/SNI (ou NULL)
 */
typedef void (*layer7_flow_cb)(const char *iface, const char *src_ip,
    const char *dst_ip, const char *app, const char *category,
    const char *host);

/*
 * Callback invocado quando uma resposta DNS (RR tipo A ou AAAA) e observada.
 *   iface:       nome da interface de captura (e.g. "em0")
 *   client_ip:   IP do cliente que recebeu a resposta (IPv4 ou IPv6 textual)
 *   domain:      nome do dominio resolvido (e.g. "youtube.com")
 *   resolved_ip: IP resolvido (dotted-quad ou textual IPv6)
 *   ttl:         TTL do record DNS (em segundos)
 */
typedef void (*layer7_dns_cb)(const char *iface, const char *client_ip,
    const char *domain, const char *resolved_ip, uint32_t ttl);

/*
 * Callback invocado quando uma query DNS (cliente -> resolver) e observada.
 *   iface:       nome da interface de captura (e.g. "em0")
 *   src_ip:      IP do cliente (IPv4 ou IPv6 textual)
 *   resolver_ip: IP do resolver de destino
 *   qname:       dominio consultado
 */
typedef void (*layer7_dns_query_cb)(const char *iface, const char *src_ip,
    const char *resolver_ip, const char *qname);

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
    layer7_flow_cb cb, layer7_dns_cb dns_cb, layer7_dns_query_cb dns_query_cb,
    const char *protos_file, char *errbuf, int errbuflen);

/*
 * Caminho A / A3: liga/desliga o uso do SNI (TLS) / Host (HTTP) extraido pelo
 * nDPI como hint de host para matching de politicas. OFF por defeito.
 * Quando ON, o host do ClientHello (ex.: "rrX.googlevideo.com") e preferido
 * sobre a heuristica de DNS reverso, melhorando bloqueio em CDNs e quando o
 * DNS do cliente esta em cache ou cifrado.
 */
void layer7_capture_set_sni(struct layer7_capture *cap, int on);

/*
 * Processa até batch_size pacotes (non-blocking se timeout_ms <= 0).
 * Retorno: número de pacotes processados, ou -1 em erro.
 */
int layer7_capture_poll(struct layer7_capture *cap, int batch_size);

/*
 * Estatísticas de captura (totais).
 */
void layer7_capture_stats(const struct layer7_capture *cap,
    unsigned long long *pkts_total, unsigned long long *flows_active,
    unsigned long long *flows_classified, unsigned long long *flows_expired,
    unsigned long long *flows_evicted, unsigned long long *flows_dropped);

/*
 * Estatísticas por família IP (passo 12.5). Ponteiros NULL são ignorados.
 */
void layer7_capture_stats_af(const struct layer7_capture *cap,
    unsigned long long *pkts_v4, unsigned long long *pkts_v6,
    unsigned long long *active_v4, unsigned long long *active_v6,
    unsigned long long *classified_v4, unsigned long long *classified_v6);

/*
 * Libera recursos (pcap_close + ndpi_exit).
 */
void layer7_capture_close(struct layer7_capture *cap);

#endif /* LAYER7_CAPTURE_H */
