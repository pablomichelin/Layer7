/*
 * dns_observe.h — Parsing partilhado de respostas DNS (A + AAAA).
 *
 * Extrai RRs tipo A (1) e AAAA (28) de uma mensagem DNS de resposta,
 * para alimentar hint cache e o callback de enforce (dns_cb).
 * Header-only para testes unitários sem ligar a pcap/nDPI.
 */
#ifndef LAYER7_DNS_OBSERVE_H
#define LAYER7_DNS_OBSERVE_H

#include <stddef.h>
#include <stdint.h>
#include <string.h>

#define LAYER7_DNS_HOST_MAX 256

/*
 * af: 4 (A / 4 bytes) ou 6 (AAAA / 16 bytes).
 * addr: ponteiro para os octetos do endereço na mensagem (não copiado).
 */
typedef void (*layer7_dns_rr_cb)(int af, const uint8_t *addr, uint32_t ttl,
    const char *qname, void *ud);

static inline int
layer7_dns_read_name(const uint8_t *msg, size_t msg_len, size_t *off,
    char *out, size_t out_len, int allow_ptr_only)
{
	size_t pos;
	size_t nout = 0;
	int jumped = 0;
	int depth = 0;

	if (!msg || !off || !out || out_len == 0)
		return -1;
	pos = *off;
	out[0] = '\0';

	for (;;) {
		uint8_t len;

		if (pos >= msg_len)
			return -1;
		len = msg[pos++];
		if (len == 0) {
			if (!jumped)
				*off = pos;
			if (nout == 0 && allow_ptr_only)
				return 0;
			return 0;
		}

		if ((len & 0xc0U) == 0xc0U) {
			size_t ptr;

			if (pos >= msg_len)
				return -1;
			ptr = ((size_t)(len & 0x3fU) << 8) | msg[pos++];
			if (ptr >= msg_len)
				return -1;
			if (!jumped) {
				*off = pos;
				jumped = 1;
			}
			pos = ptr;
			if (++depth > 8)
				return -1;
			continue;
		}

		if (len > 63 || pos + len > msg_len)
			return -1;
		if (nout != 0) {
			if (nout + 1 >= out_len)
				return -1;
			out[nout++] = '.';
		}
		if (nout + len >= out_len)
			return -1;
		memcpy(out + nout, msg + pos, len);
		nout += len;
		out[nout] = '\0';
		pos += len;
	}

	return -1;
}

/*
 * Percorre answers de uma resposta DNS (exige QR=1 no header).
 * Invoca cb para cada RR A (af=4) ou AAAA (af=6).
 * Retorna o número de RRs A/AAAA entregues, ou -1 se o cabeçalho/QNAME falhar.
 */
static inline int
layer7_dns_foreach_a_aaaa(const uint8_t *payload, size_t payload_len,
    layer7_dns_rr_cb cb, void *ud)
{
	size_t off;
	uint16_t qd, an, i;
	char qname[LAYER7_DNS_HOST_MAX];
	char rrname[LAYER7_DNS_HOST_MAX];
	int delivered = 0;

	if (!payload || payload_len < 12 || !cb)
		return -1;

	/* QR=1 obrigatório — caller pode mentir; não tratar query como answer. */
	if ((payload[2] & 0x80U) == 0)
		return -1;

	qd = (uint16_t)((payload[4] << 8) | payload[5]);
	an = (uint16_t)((payload[6] << 8) | payload[7]);
	if (qd == 0 || an == 0)
		return 0;

	off = 12;
	if (layer7_dns_read_name(payload, payload_len, &off, qname,
	    sizeof(qname), 0) != 0)
		return -1;
	if (off + 4 > payload_len)
		return -1;
	off += 4; /* qtype + qclass */

	for (i = 0; i < an && off < payload_len; i++) {
		uint16_t type, class_, rdlen;
		uint32_t ttl;

		if (layer7_dns_read_name(payload, payload_len, &off, rrname,
		    sizeof(rrname), 0) != 0)
			return delivered > 0 ? delivered : -1;
		if (off + 10 > payload_len)
			return delivered > 0 ? delivered : -1;
		type = (uint16_t)((payload[off] << 8) | payload[off + 1]);
		class_ = (uint16_t)((payload[off + 2] << 8) | payload[off + 3]);
		ttl = ((uint32_t)payload[off + 4] << 24) |
		    ((uint32_t)payload[off + 5] << 16) |
		    ((uint32_t)payload[off + 6] << 8) |
		    (uint32_t)payload[off + 7];
		rdlen = (uint16_t)((payload[off + 8] << 8) | payload[off + 9]);
		off += 10;
		if (off + rdlen > payload_len)
			return delivered > 0 ? delivered : -1;
		if (class_ == 1 && type == 1 && rdlen == 4) {
			cb(4, payload + off, ttl, qname, ud);
			delivered++;
		} else if (class_ == 1 && type == 28 && rdlen == 16) {
			cb(6, payload + off, ttl, qname, ud);
			delivered++;
		}
		off += rdlen;
	}
	return delivered;
}

#endif /* LAYER7_DNS_OBSERVE_H */
