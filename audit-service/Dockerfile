# Audit service image (Node + TypeScript).

# ---- Stage 1: compile TypeScript to JavaScript ----
FROM node:24-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY tsconfig.json ./
COPY src ./src
RUN npm run build

# ---- Stage 2: small runtime image with production packages only ----
FROM node:24-alpine
ENV NODE_ENV=production
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev && npm cache clean --force
COPY --from=build /app/dist ./dist
COPY docker-entrypoint.sh ./

# Don't run as root.
USER node

EXPOSE 4000
ENTRYPOINT ["sh", "docker-entrypoint.sh"]
CMD ["node", "dist/server.js"]
