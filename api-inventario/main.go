package main

// Responsable: Ricardo
// api-inventario: stock por producto guardado en memoria (map + sync.Mutex).

import (
	"net/http"
	"os"
	"strconv"
	"sync"

	"github.com/gin-gonic/gin"
)

type Reserva struct {
	ProductoID int `json:"producto_id" binding:"required,min=1"`
	Cantidad   int `json:"cantidad" binding:"required,min=1"`
}

// Inventario en memoria. El mutex evita que dos reservas simultáneas
// descuenten el mismo stock (condición de carrera).
type Inventario struct {
	mu    sync.Mutex
	stock map[int]int
}

func nuevoInventario() *Inventario {
	// Datos semilla: productos 1 a 10 (los mismos IDs que api-productos)
	return &Inventario{stock: map[int]int{
		1: 50, 2: 30, 3: 25, 4: 40, 5: 15,
		6: 60, 7: 10, 8: 35, 9: 20, 10: 45,
	}}
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

func errorJSON(c *gin.Context, status int, msg string) {
	c.AbortWithStatusJSON(status, gin.H{"error": msg})
}

// leerReserva valida el body; si falta algo responde 400 con { "error": ... }
func leerReserva(c *gin.Context) (Reserva, bool) {
	var r Reserva
	if err := c.ShouldBindJSON(&r); err != nil {
		errorJSON(c, http.StatusBadRequest, "producto_id y cantidad son obligatorios y deben ser enteros mayores a 0")
		return r, false
	}
	return r, true
}

func setupRouter(inv *Inventario) *gin.Engine {
	r := gin.Default()
	r.Use(cors())

	r.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"servicio": "api-inventario", "ok": true})
	})

	// GET /api/inventario/:producto_id -> { producto_id, disponibles } o 404
	r.GET("/api/inventario/:producto_id", func(c *gin.Context) {
		id, err := strconv.Atoi(c.Param("producto_id"))
		if err != nil || id <= 0 {
			errorJSON(c, http.StatusBadRequest, "producto_id debe ser un entero positivo")
			return
		}
		inv.mu.Lock()
		disp, ok := inv.stock[id]
		inv.mu.Unlock()
		if !ok {
			errorJSON(c, http.StatusNotFound, "Producto no encontrado en inventario")
			return
		}
		c.JSON(http.StatusOK, gin.H{"producto_id": id, "disponibles": disp})
	})

	// POST /api/inventario/reservar { producto_id, cantidad } -> descuenta; 400 si no alcanza
	r.POST("/api/inventario/reservar", func(c *gin.Context) {
		req, ok := leerReserva(c)
		if !ok {
			return
		}
		inv.mu.Lock()
		defer inv.mu.Unlock()
		disp, existe := inv.stock[req.ProductoID]
		if !existe {
			errorJSON(c, http.StatusNotFound, "Producto no encontrado en inventario")
			return
		}
		if disp < req.Cantidad {
			errorJSON(c, http.StatusBadRequest,
				"Stock insuficiente: disponibles "+strconv.Itoa(disp)+", solicitados "+strconv.Itoa(req.Cantidad))
			return
		}
		inv.stock[req.ProductoID] = disp - req.Cantidad
		c.JSON(http.StatusOK, gin.H{
			"producto_id": req.ProductoID,
			"reservados":  req.Cantidad,
			"disponibles": inv.stock[req.ProductoID],
		})
	})

	// POST /api/inventario/reabastecer { producto_id, cantidad } -> suma unidades
	// Si el producto no existía, se da de alta con esa cantidad.
	r.POST("/api/inventario/reabastecer", func(c *gin.Context) {
		req, ok := leerReserva(c)
		if !ok {
			return
		}
		inv.mu.Lock()
		inv.stock[req.ProductoID] += req.Cantidad
		disp := inv.stock[req.ProductoID]
		inv.mu.Unlock()
		c.JSON(http.StatusOK, gin.H{
			"producto_id": req.ProductoID,
			"agregados":   req.Cantidad,
			"disponibles": disp,
		})
	})

	r.NoRoute(func(c *gin.Context) { errorJSON(c, http.StatusNotFound, "Ruta no encontrada") })
	return r
}

func main() {
	setupRouter(nuevoInventario()).Run(":8004")
}
