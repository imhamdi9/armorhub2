# Production image for the Next.js web app.
# NEXT_PUBLIC_API_URL is inlined at build time:
#   docker build --build-arg NEXT_PUBLIC_API_URL=/api --build-arg API_PROXY_TARGET=https://api.example.com -t armorhub-web apps/web
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

FROM node:22-alpine AS build
WORKDIR /app
# NEXT_PUBLIC_API_URL=/api + API_PROXY_TARGET=<api origin> serve the API from the web app's own origin
# (recommended: no CORS, the session cookie stays first-party).
ARG NEXT_PUBLIC_API_URL
ARG API_PROXY_TARGET
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL \
    API_PROXY_TARGET=$API_PROXY_TARGET \
    NEXT_TELEMETRY_DISABLED=1
COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build

FROM node:22-alpine AS run
WORKDIR /app
# Server-rendered public pages read the API directly at runtime.
ARG API_PROXY_TARGET
ENV API_INTERNAL_URL=$API_PROXY_TARGET
ENV NODE_ENV=production \
    NEXT_TELEMETRY_DISABLED=1 \
    PORT=3000 \
    HOSTNAME=0.0.0.0
COPY --from=build /app/public ./public
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
USER node
EXPOSE 3000
CMD ["node", "server.js"]
