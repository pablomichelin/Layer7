#ifndef LAYER7_LOG_STORE_H
#define LAYER7_LOG_STORE_H

#include <stddef.h>

/*
 * Append atomico com rotacao local limitada.
 *
 * `max_bytes` e o limite aproximado do ficheiro activo. Quando a proxima
 * linha ultrapassaria o limite, `path` passa a `path.1` e as copias antigas
 * avancam ate `path.<keep_files>`.
 */
int layer7_log_store_append(const char *path, const char *line,
    size_t max_bytes, unsigned int keep_files);

int layer7_log_store_rotate(const char *path, unsigned int keep_files);

#endif
