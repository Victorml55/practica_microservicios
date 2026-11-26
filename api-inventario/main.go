package main

// Responsable: Ricardo

import (
	"net/http"
	"os"

	"github.com/gin-gonic/gin"
)

type Reserva struct {
	ProductoID int `json:"producto_id" binding:"required"`
	Cantidad   int `json:"cantidad" binding:"required,min=1"`
}

func cors() gin.HandlerFunc {
	origin := os.Getenv("CORS_ORIGIN")
	if origin == "" {
		origin = "*"
	}
	return func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", origin)
		c.Header("Access-Control-Allow-Methods", "GET, POST, PATCH, DELETE, OPTIONS")
		c.Header("Access-Control-Allow-Headers", "Content-Type, Authorization")
		if c.Request.Method == http.MethodOptions {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}
		c.Next()
	}
}

func noImpl(c *gin.Context) {
	c.JSON(http.StatusNotImplemented, gin.H{"error": "No implementado"})
}

func main() {
	r := gin.Default()
	r.Use(cors())

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"servicio": "api-inventario", "ok": true})
	})

	// TODO: { producto_id, disponibles } o 404
	r.GET("/api/inventario/:producto_id", noImpl)
	// TODO: body Reserva -> descontar; 400 si no hay suficiente stock
	r.POST("/api/inventario/reservar", noImpl)
	// TODO: body Reserva -> sumar unidades
	r.POST("/api/inventario/reabastecer", noImpl)

	r.Run(":8004")
}
