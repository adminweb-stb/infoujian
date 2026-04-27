# Stage 1: Build Frontend (Vite)
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Production Server (Express)
FROM node:20-alpine
WORKDIR /app

# Copy package and install production dependencies only
COPY package*.json ./
RUN npm install --omit=dev

# Copy compiled frontend from builder
COPY --from=builder /app/dist ./dist

# Copy backend and static files
COPY server.js ./
COPY logger.js ./
COPY control-center ./control-center

# Set environment to production
ENV NODE_ENV=production
ENV PORT=3000

# Expose port
EXPOSE 3000

# Start server
CMD ["node", "server.js"]
