#include "log_store.h"

#include <errno.h>
#include <fcntl.h>
#include <stdio.h>
#include <string.h>
#include <sys/stat.h>
#include <unistd.h>

#define L7_LOG_PATH_MAX 512
#define L7_LOG_KEEP_MAX 10

static int
rotated_path(char *out, size_t out_len, const char *path, unsigned int idx)
{
	int n;

	n = snprintf(out, out_len, "%s.%u", path, idx);
	if (n < 0 || (size_t)n >= out_len) {
		errno = ENAMETOOLONG;
		return -1;
	}
	return 0;
}

int
layer7_log_store_rotate(const char *path, unsigned int keep_files)
{
	char src[L7_LOG_PATH_MAX], dst[L7_LOG_PATH_MAX];
	unsigned int i;

	if (!path || path[0] == '\0' || keep_files > L7_LOG_KEEP_MAX) {
		errno = EINVAL;
		return -1;
	}
	if (keep_files == 0)
		return unlink(path) == 0 || errno == ENOENT ? 0 : -1;

	if (rotated_path(dst, sizeof(dst), path, keep_files) != 0)
		return -1;
	if (unlink(dst) != 0 && errno != ENOENT)
		return -1;

	for (i = keep_files; i > 1; i--) {
		if (rotated_path(src, sizeof(src), path, i - 1) != 0 ||
		    rotated_path(dst, sizeof(dst), path, i) != 0)
			return -1;
		if (rename(src, dst) != 0 && errno != ENOENT)
			return -1;
	}

	if (rotated_path(dst, sizeof(dst), path, 1) != 0)
		return -1;
	if (rename(path, dst) != 0 && errno != ENOENT)
		return -1;
	return 0;
}

static int
write_all(int fd, const char *buf, size_t len)
{
	size_t off = 0;

	while (off < len) {
		ssize_t n = write(fd, buf + off, len - off);
		if (n < 0) {
			if (errno == EINTR)
				continue;
			return -1;
		}
		if (n == 0) {
			errno = EIO;
			return -1;
		}
		off += (size_t)n;
	}
	return 0;
}

int
layer7_log_store_append(const char *path, const char *line,
    size_t max_bytes, unsigned int keep_files)
{
	struct stat st;
	size_t len;
	int fd, flags;

	if (!path || path[0] == '\0' || !line || max_bytes == 0 ||
	    keep_files > L7_LOG_KEEP_MAX) {
		errno = EINVAL;
		return -1;
	}
	len = strlen(line);
	if (stat(path, &st) == 0) {
		if (!S_ISREG(st.st_mode)) {
			errno = EINVAL;
			return -1;
		}
		if ((size_t)st.st_size + len + 1 > max_bytes &&
		    layer7_log_store_rotate(path, keep_files) != 0)
			return -1;
	} else if (errno != ENOENT) {
		return -1;
	}

	flags = O_WRONLY | O_CREAT | O_APPEND;
#ifdef O_CLOEXEC
	flags |= O_CLOEXEC;
#endif
#ifdef O_NOFOLLOW
	flags |= O_NOFOLLOW;
#endif
	fd = open(path, flags, 0600);
	if (fd < 0)
		return -1;
	if (fstat(fd, &st) != 0 || !S_ISREG(st.st_mode) ||
	    write_all(fd, line, len) != 0 || write_all(fd, "\n", 1) != 0) {
		int saved_errno = errno ? errno : EIO;
		close(fd);
		errno = saved_errno;
		return -1;
	}
	if (close(fd) != 0)
		return -1;
	return 0;
}
