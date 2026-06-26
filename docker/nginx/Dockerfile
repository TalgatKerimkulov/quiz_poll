# Use the official Nginx image
FROM nginx:alpine

# Set the working directory inside the container
WORKDIR /app

# Copy the local code into the container
COPY . /app

# Copy custom Nginx configuration
COPY ./docker/nginx/production.conf /etc/nginx/conf.d/default.conf

# Expose port 80 for Nginx
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
