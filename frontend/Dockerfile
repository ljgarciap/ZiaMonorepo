# Stage 1: Build the Angular App
FROM node:20-alpine AS build
WORKDIR /app
# SHA del build (pasado por CI vía --build-arg), servido como archivo estático
# para que el pipeline pueda confirmar que el deploy sirve el commit esperado
# en vez de asumirlo — ver deploy-vps.yml "Wait for frontend deploy".
ARG IMAGE_TAG=unknown
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build -- --configuration=production
RUN echo "$IMAGE_TAG" > dist/Zia_Frontend/browser/version.txt

# Stage 2: Serve the App using Nginx
FROM nginx:alpine
COPY --from=build /app/dist/Zia_Frontend/browser /usr/share/nginx/html
COPY ./nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
