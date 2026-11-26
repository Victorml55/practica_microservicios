FROM php:8.3-cli
WORKDIR /app
COPY . .
EXPOSE 8001
CMD ["php", "-S", "0.0.0.0:8001", "-t", "public", "public/index.php"]
