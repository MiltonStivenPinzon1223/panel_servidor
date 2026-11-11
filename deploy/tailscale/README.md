# Tailscale Serve privado

Con Nginx escuchando solo en `127.0.0.1:8081`, en el servidor ejecuta el comando documentado:

```sh
tailscale serve --bg http://127.0.0.1:8081
tailscale serve status
```

La ayuda instalada confirma el destino HTTP local y el modo en segundo plano. Verifica desde el celular que el nombre HTTPS MagicDNS funcione cuando ambos estén en la tailnet. No habilites `tailscale funnel`; no publiques el listener 8081 ni el puerto 80. `100.109.93.93` es la IP actual anotada en la configuración inicial, no una IP fija.
