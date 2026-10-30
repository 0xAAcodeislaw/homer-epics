import { Container } from "@cloudflare/containers";

export class TypechoContainer extends Container {
  defaultPort = 80;
  sleepAfter = "10m";
}

interface Env {
  TYPECHO_CONTAINER: DurableObjectNamespace;
}

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const id = env.TYPECHO_CONTAINER.idFromName("singleton");
    const stub = env.TYPECHO_CONTAINER.get(id);
    return stub.fetch(request);
  },
};
