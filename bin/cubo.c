#include <windows.h>
#include <process.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#define MAX_ARGS 128

static void ownDirectory(char *out, size_t size)
{
    char path[MAX_PATH];
    DWORD len = GetModuleFileNameA(NULL, path, MAX_PATH);
    char *slash;

    if (len == 0 || len == MAX_PATH) {
        fprintf(stderr, "cubo: nao foi possivel resolver o caminho do executavel\n");
        exit(1);
    }

    slash = strrchr(path, '\\');
    if (slash != NULL) {
        *slash = '\0';
    }

    snprintf(out, size, "%s", path);
}

int main(int argc, char *argv[])
{
    char dir[MAX_PATH];
    char script[MAX_PATH + 32];
    const char *args[MAX_ARGS];
    int i;
    intptr_t status;

    if (argc > MAX_ARGS - 3) {
        fprintf(stderr, "cubo: argumentos demais fdp\n");
        return 1;
    }

    ownDirectory(dir, sizeof(dir));
    snprintf(script, sizeof(script), "\"%s\\cubo.php\"", dir);

    args[0] = "php";
    args[1] = script;
    for (i = 1; i < argc; i++) {
        args[i + 1] = argv[i];
    }
    args[argc + 1] = NULL;

    status = _spawnvp(_P_WAIT, "php", args);

    if (status == -1) {
        fprintf(stderr, "cubo: nao encontrei o php no PATH\n");
        return 1;
    }

    return (int) status;
}
