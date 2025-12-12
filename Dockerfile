FROM node:24-alpine AS build
WORKDIR /app
ENV MONGOMS_DISABLE_POSTINSTALL=1
COPY package*.json ./
COPY client/package.json client/package.json
COPY server/package.json server/package.json
RUN npm ci
COPY client client
RUN npm run build

FROM node:24-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
ENV MONGOMS_DISABLE_POSTINSTALL=1
COPY package*.json ./
COPY client/package.json client/package.json
COPY server/package.json server/package.json
RUN npm ci --omit=dev --workspace server --include-workspace-root=false && npm cache clean --force
COPY server/src server/src
COPY --from=build /app/client/dist client/dist
RUN mkdir -p server/uploads && chown -R node:node /app
USER node
EXPOSE 4000
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s CMD node -e "fetch('http://127.0.0.1:4000/api/health').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "server/src/index.js"]
