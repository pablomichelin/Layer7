/*
 * layer7d — daemon V1 (Bloco 6): syslog, sinais, ficheiro de config (presença).
 * Config JSON completa: parser futuro; caminho omissão /usr/local/etc/layer7.json
 */
#include <errno.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/stat.h>
#include <syslog.h>
#include <unistd.h>

#define DEFAULT_CONFIG "/usr/local/etc/layer7.json"

static volatile sig_atomic_t stop_req;
static volatile sig_atomic_t reload_req;
static const char *config_path = DEFAULT_CONFIG;

static void on_signal(int sig)
{
	(void)sig;
	stop_req = 1;
}

static void on_hup(int sig)
{
	(void)sig;
	reload_req = 1;
}

static void usage(void)
{
	fprintf(stderr, "usage: layer7d [-c /path/layer7.json]\n");
}

int main(int argc, char **argv)
{
	struct sigaction sa;
	struct stat st;
	int i;

	for (i = 1; i < argc; i++) {
		if (strcmp(argv[i], "-c") == 0 && i + 1 < argc) {
			config_path = argv[++i];
			continue;
		}
		if (strcmp(argv[i], "-h") == 0 || strcmp(argv[i], "--help") == 0) {
			usage();
			return 0;
		}
		fprintf(stderr, "layer7d: unknown argument: %s\n", argv[i]);
		usage();
		return 1;
	}

	sa.sa_handler = on_signal;
	sigemptyset(&sa.sa_mask);
	sa.sa_flags = 0;
	sigaction(SIGTERM, &sa, NULL);
	sigaction(SIGINT, &sa, NULL);
	sa.sa_handler = on_hup;
	sigaction(SIGHUP, &sa, NULL);

	openlog("layer7d", LOG_PID | LOG_CONS, LOG_DAEMON);
	syslog(LOG_NOTICE, "daemon_start");

	if (stat(config_path, &st) == 0)
		syslog(LOG_NOTICE, "config file present: %s (%lld bytes)",
		    config_path, (long long)st.st_size);
	else if (errno == ENOENT)
		syslog(LOG_NOTICE,
		    "config file absent: %s (defaults until GUI persist / copy sample)",
		    config_path);
	else
		syslog(LOG_WARNING, "config path %s: %s", config_path,
		    strerror(errno));

	for (;;) {
		if (stop_req) {
			syslog(LOG_NOTICE, "daemon_stop");
			closelog();
			return 0;
		}
		if (reload_req) {
			reload_req = 0;
			syslog(LOG_NOTICE,
			    "SIGHUP received (reload not implemented yet)");
		}
		sleep(30);
	}
}
