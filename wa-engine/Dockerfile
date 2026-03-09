# Menggunakan Node.js versi ringan
FROM node:18-slim

# Menginstal Chromium dan library pendukungnya agar bot WA tidak error
RUN apt-get update && apt-get install -y chromium \
    && rm -rf /var/lib/apt/lists/*

# Pindah ke direktori kerja
WORKDIR /app

# Salin package.json dan install dependency
COPY package*.json ./
RUN npm install

# Salin seluruh kode Node.js Anda
COPY . .

# Jalankan bot
CMD ["node", "index.js"]