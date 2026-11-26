package com.tienda.pagos;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.context.annotation.Bean;
import org.springframework.web.servlet.config.annotation.CorsRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

// Responsable: Dennis
@SpringBootApplication
public class PagosApplication {

    public static void main(String[] args) {
        SpringApplication.run(PagosApplication.class, args);
    }

    @Bean
    public WebMvcConfigurer corsConfigurer(@Value("${cors.origin}") String origin) {
        return new WebMvcConfigurer() {
            @Override
            public void addCorsMappings(CorsRegistry registry) {
                registry.addMapping("/**").allowedOrigins(origin)
                        .allowedMethods("GET", "POST", "PATCH", "DELETE", "OPTIONS");
            }
        };
    }
}
