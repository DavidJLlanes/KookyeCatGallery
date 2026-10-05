/* 100 photographic recipes, designed independently. */
window.photoEditorPresetData = {
  "version": 2,
  "families": [
    {
      "id": "vintage",
      "title": "Vintage · película envejecida"
    },
    {
      "id": "instant",
      "title": "Instantáneas y papel"
    },
    {
      "id": "cross",
      "title": "Procesos cruzados"
    },
    {
      "id": "cinema",
      "title": "Cine y revelado"
    },
    {
      "id": "toned",
      "title": "Virados y técnicas históricas"
    },
    {
      "id": "mono",
      "title": "Blanco y negro"
    },
    {
      "id": "camera",
      "title": "Lomo y cámaras"
    },
    {
      "id": "color",
      "title": "Color creativo"
    },
    {
      "id": "night",
      "title": "Nocturnos y neón"
    },
    {
      "id": "experimental",
      "title": "Laboratorio experimental"
    }
  ],
  "presets": [
    {
      "id": "look-001",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Álbum 1962",
      "description": "Color desvaído, negros cálidos lavados, papel crema y grano suave.",
      "grade": {
        "curve": [
          0.16,
          0.34,
          0.55,
          0.73,
          0.88
        ],
        "saturation": 0.54,
        "temperature": 0.25,
        "channels": [
          [
            0.07,
            0.32,
            0.58,
            0.79,
            0.96
          ],
          [
            0.07,
            0.31,
            0.54,
            0.74,
            0.89
          ],
          [
            0.09,
            0.25,
            0.43,
            0.62,
            0.78
          ]
        ],
        "hsl": {
          "green": [
            -25,
            0.6,
            0.01
          ],
          "blue": [
            -12,
            0.6,
            0.02
          ]
        },
        "grain": {
          "amount": 0.23,
          "size": 1.8,
          "color": 0.12
        },
        "softness": 0.18,
        "vignette": {
          "amount": 0.25,
          "color": "#675140",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-002",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Postal de los setenta",
      "description": "Amarillos mostaza, verdes oliva y esquinas marrones.",
      "grade": {
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "saturation": 0.76,
        "temperature": 0.4,
        "channels": [
          [
            0.03,
            0.27,
            0.58,
            0.84,
            0.98
          ],
          [
            0.08,
            0.32,
            0.57,
            0.78,
            0.92
          ],
          [
            0.03,
            0.16,
            0.34,
            0.56,
            0.77
          ]
        ],
        "hsl": {
          "green": [
            -35,
            0.65,
            -0.03
          ],
          "yellow": [
            -12,
            0.8,
            0
          ],
          "blue": [
            -15,
            0.5,
            -0.05
          ]
        },
        "grain": {
          "amount": 0.32,
          "size": 1.6,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.4,
          "color": "#302012",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-003",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Diapositiva 1984",
      "description": "Rojos densos, azul profundo, contraste de diapositiva y halo cálido.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.38,
        "temperature": 0.12,
        "hsl": {
          "red": [
            -6,
            1.2,
            -0.04
          ],
          "blue": [
            8,
            1.25,
            -0.09
          ],
          "green": [
            -9,
            0.85,
            -0.04
          ]
        },
        "channels": [
          [
            0.015,
            0.2,
            0.55,
            0.86,
            1
          ],
          [
            0,
            0.19,
            0.45,
            0.77,
            0.97
          ],
          [
            0.07,
            0.28,
            0.52,
            0.84,
            1
          ]
        ],
        "grain": {
          "amount": 0.18,
          "size": 0.8,
          "color": 0.12
        },
        "halation": {
          "amount": 0.48,
          "radius": 0.009,
          "threshold": 0.65
        },
        "vignette": {
          "amount": 0.26,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-004",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Flash de los noventa",
      "description": "Blancos fríos de flash, sombras cian y negros duros.",
      "grade": {
        "curve": [
          0.005,
          0.14,
          0.54,
          0.94,
          1
        ],
        "saturation": 0.88,
        "exposure": 0.18,
        "channels": [
          [
            0,
            0.2,
            0.48,
            0.82,
            0.97
          ],
          [
            0.03,
            0.27,
            0.56,
            0.89,
            1
          ],
          [
            0.1,
            0.32,
            0.59,
            0.92,
            1
          ]
        ],
        "grain": {
          "amount": 0.28,
          "size": 0.7,
          "color": 0.2
        },
        "vignette": {
          "amount": 0.12,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        },
        "split": {
          "shadows": "#30757d",
          "highlights": "#d4e9ff",
          "amount": 0.18,
          "balance": 0.5
        }
      }
    },
    {
      "id": "look-005",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Negativo caducado",
      "description": "Dominante verde de emulsión vencida, grano grande y fuga roja.",
      "grade": {
        "curve": [
          0.12,
          0.3,
          0.47,
          0.66,
          0.9
        ],
        "saturation": 0.7,
        "channels": [
          [
            0.08,
            0.25,
            0.5,
            0.8,
            0.98
          ],
          [
            0.13,
            0.4,
            0.68,
            0.87,
            0.99
          ],
          [
            0.08,
            0.22,
            0.44,
            0.65,
            0.83
          ]
        ],
        "grain": {
          "amount": 0.5,
          "size": 2.8,
          "color": 0.35
        },
        "leaks": [
          {
            "color": "#ff3b12",
            "amount": 0.78,
            "position": [
              0,
              0.55
            ],
            "radius": 0.8,
            "stretch": 0.5
          }
        ],
        "vignette": {
          "amount": 0.38,
          "color": "#24391c",
          "radius": 0.35,
          "feather": 0.9
        },
        "dust": {
          "count": 28,
          "amount": 0.35,
          "size": 2
        }
      }
    },
    {
      "id": "look-006",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Emulsión violeta",
      "description": "Pérdida de verdes, sombras malva y luces rosadas de película vieja.",
      "grade": {
        "curve": [
          0.16,
          0.34,
          0.55,
          0.73,
          0.88
        ],
        "saturation": 0.72,
        "channels": [
          [
            0.15,
            0.36,
            0.61,
            0.83,
            0.99
          ],
          [
            0.055,
            0.19,
            0.38,
            0.65,
            0.87
          ],
          [
            0.2,
            0.42,
            0.62,
            0.82,
            0.96
          ]
        ],
        "grain": {
          "amount": 0.3,
          "size": 1.7,
          "color": 0.3
        },
        "bloom": {
          "amount": 0.18,
          "radius": 0.03,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.22,
          "color": "#40284d",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-007",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Copia guardada",
      "description": "Foto de papel amarillento, contraste bajo y motas de polvo.",
      "grade": {
        "curve": [
          0.2,
          0.37,
          0.56,
          0.7,
          0.84
        ],
        "saturation": 0.32,
        "temperature": 0.32,
        "channels": [
          [
            0.09,
            0.33,
            0.58,
            0.82,
            0.95
          ],
          [
            0.09,
            0.3,
            0.53,
            0.74,
            0.85
          ],
          [
            0.055,
            0.22,
            0.42,
            0.6,
            0.72
          ]
        ],
        "grain": {
          "amount": 0.2,
          "size": 2,
          "color": 0.12
        },
        "softness": 0.3,
        "vignette": {
          "amount": 0.32,
          "color": "#ece1bb",
          "radius": 0.45,
          "feather": 0.7
        },
        "dust": {
          "count": 35,
          "amount": 0.55,
          "size": 2.8
        }
      }
    },
    {
      "id": "look-008",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Azul de mercadillo",
      "description": "Copia retro fría, sombras abiertas y azul deslavado.",
      "grade": {
        "curve": [
          0.14,
          0.32,
          0.51,
          0.72,
          0.91
        ],
        "saturation": 0.6,
        "temperature": -0.24,
        "channels": [
          [
            0.08,
            0.23,
            0.4,
            0.68,
            0.92
          ],
          [
            0.13,
            0.34,
            0.55,
            0.78,
            0.96
          ],
          [
            0.24,
            0.43,
            0.66,
            0.86,
            1
          ]
        ],
        "hsl": {
          "green": [
            16,
            0.6,
            0.04
          ]
        },
        "grain": {
          "amount": 0.36,
          "size": 1.4,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.28,
          "color": "#edf5fa",
          "radius": 0.35,
          "feather": 0.9
        },
        "softness": 0.12
      }
    },
    {
      "id": "look-009",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Recuerdo quemado por el sol",
      "description": "Color desteñido, rojos anaranjados y una esquina velada por la luz.",
      "grade": {
        "curve": [
          0.12,
          0.4,
          0.68,
          0.84,
          0.97
        ],
        "saturation": 0.46,
        "temperature": 0.55,
        "hsl": {
          "red": [
            18,
            0.8,
            0.06
          ],
          "green": [
            -30,
            0.45,
            0.05
          ],
          "blue": [
            -10,
            0.35,
            0.08
          ]
        },
        "grain": {
          "amount": 0.25,
          "size": 1.6,
          "color": 0.12
        },
        "leaks": [
          {
            "color": "#ffe2a0",
            "amount": 0.9,
            "position": [
              0.95,
              0.15
            ],
            "radius": 1,
            "stretch": 0.8
          }
        ],
        "vignette": {
          "amount": 0.26,
          "color": "#f4dcb2",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-010",
      "category": "vintage",
      "group": "Vintage · película envejecida",
      "name": "Laboratorio de barrio",
      "description": "Negativo cálido de alto grano, negros rojizos y verde apagado.",
      "grade": {
        "curve": [
          0.08,
          0.22,
          0.42,
          0.66,
          0.86
        ],
        "saturation": 0.58,
        "channels": [
          [
            0.2,
            0.39,
            0.63,
            0.85,
            0.98
          ],
          [
            0.035,
            0.18,
            0.42,
            0.7,
            0.95
          ],
          [
            0.08,
            0.27,
            0.56,
            0.82,
            1
          ]
        ],
        "grain": {
          "amount": 0.48,
          "size": 1.1,
          "color": 0.12
        },
        "halation": {
          "amount": 0.65,
          "radius": 0.014,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.4,
          "color": "#381911",
          "radius": 0.35,
          "feather": 0.9
        },
        "hsl": {
          "green": [
            -12,
            0.6,
            -0.03
          ]
        }
      }
    },
    {
      "id": "look-011",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Instantánea crema",
      "description": "Papel crema, verde suave y luz difusa de cámara instantánea.",
      "grade": {
        "curve": [
          0.035,
          0.29,
          0.58,
          0.8,
          0.97
        ],
        "saturation": 0.64,
        "temperature": 0.2,
        "channels": [
          [
            0.09,
            0.32,
            0.58,
            0.79,
            0.94
          ],
          [
            0.075,
            0.35,
            0.6,
            0.8,
            0.94
          ],
          [
            0.07,
            0.26,
            0.44,
            0.69,
            0.85
          ]
        ],
        "softness": 0.4,
        "bloom": {
          "amount": 0.22,
          "radius": 0.025,
          "threshold": 0.55
        },
        "grain": {
          "amount": 0.14,
          "size": 2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.28,
          "color": "#f5e6c9",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-012",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Polaroid chocolate",
      "description": "Negros de cacao, rojizos de película y luz amarilla.",
      "grade": {
        "curve": [
          0.055,
          0.16,
          0.33,
          0.61,
          0.86
        ],
        "saturation": 1.2,
        "channels": [
          [
            0.15,
            0.37,
            0.61,
            0.8,
            0.95
          ],
          [
            0.07,
            0.24,
            0.44,
            0.65,
            0.84
          ],
          [
            0.025,
            0.15,
            0.33,
            0.53,
            0.74
          ]
        ],
        "softness": 0.22,
        "grain": {
          "amount": 0.25,
          "size": 2.2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.57,
          "color": "#442719",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-013",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Packfilm menta",
      "description": "Sombras verdes de papel instantáneo y blancos verdosos.",
      "grade": {
        "curve": [
          0.13,
          0.36,
          0.59,
          0.78,
          0.96
        ],
        "saturation": 0.82,
        "channels": [
          [
            0.04,
            0.2,
            0.41,
            0.65,
            0.87
          ],
          [
            0.13,
            0.38,
            0.65,
            0.86,
            1
          ],
          [
            0.15,
            0.4,
            0.64,
            0.8,
            0.96
          ]
        ],
        "grain": {
          "amount": 0.17,
          "size": 1.7,
          "color": 0.12
        },
        "bloom": {
          "amount": 0.12,
          "radius": 0.018,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.22,
          "color": "#b6d8c7",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-014",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Instantánea rosa",
      "description": "Veladura rosa, blanco pastel y bordes claros.",
      "grade": {
        "curve": [
          0.1,
          0.38,
          0.64,
          0.84,
          1
        ],
        "saturation": 0.52,
        "channels": [
          [
            0.13,
            0.37,
            0.64,
            0.86,
            1
          ],
          [
            0.045,
            0.25,
            0.46,
            0.72,
            0.91
          ],
          [
            0.14,
            0.37,
            0.62,
            0.83,
            1
          ]
        ],
        "softness": 0.18,
        "grain": {
          "amount": 0.16,
          "size": 1.5,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.36,
          "color": "#ffcee3",
          "radius": 0.45,
          "feather": 0.8
        }
      }
    },
    {
      "id": "look-015",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Fiesta de un solo uso",
      "description": "Destello frontal, rojos vivos, fuga ámbar y grano de ISO alto.",
      "grade": {
        "exposure": 0.2,
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.25,
        "temperature": 0.08,
        "grain": {
          "amount": 0.42,
          "size": 1,
          "color": 0.24
        },
        "leaks": [
          {
            "color": "#ff9a28",
            "amount": 0.75,
            "position": [
              0,
              0.28
            ],
            "radius": 0.7,
            "stretch": 0.35
          }
        ],
        "vignette": {
          "amount": 0.55,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        },
        "halation": {
          "amount": 0.3,
          "radius": 0.012,
          "threshold": 0.72
        }
      }
    },
    {
      "id": "look-016",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Instantánea despegada",
      "description": "Copia lechosa con negros azulados y blancos lavados.",
      "grade": {
        "curve": [
          0.21,
          0.43,
          0.6,
          0.73,
          0.84
        ],
        "saturation": 0.35,
        "channels": [
          [
            0.06,
            0.28,
            0.49,
            0.72,
            0.93
          ],
          [
            0.07,
            0.28,
            0.52,
            0.74,
            0.94
          ],
          [
            0.19,
            0.4,
            0.59,
            0.78,
            0.96
          ]
        ],
        "softness": 0.5,
        "bloom": {
          "amount": 0.35,
          "radius": 0.035,
          "threshold": 0.45
        },
        "vignette": {
          "amount": 0.5,
          "color": "#f4f1e9",
          "radius": 0.3,
          "feather": 0.7
        },
        "dust": {
          "count": 16,
          "amount": 0.4,
          "size": 2.4
        }
      }
    },
    {
      "id": "look-017",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Transferencia índigo",
      "description": "Transferencia de emulsión fría, sombras de tinta y color desgastado.",
      "grade": {
        "curve": [
          0.045,
          0.17,
          0.4,
          0.73,
          0.91
        ],
        "saturation": 0.7,
        "channels": [
          [
            0.03,
            0.16,
            0.4,
            0.71,
            0.94
          ],
          [
            0.02,
            0.12,
            0.37,
            0.69,
            0.9
          ],
          [
            0.16,
            0.35,
            0.61,
            0.87,
            1
          ]
        ],
        "grain": {
          "amount": 0.32,
          "size": 2.4,
          "color": 0.15
        },
        "vignette": {
          "amount": 0.48,
          "color": "#241b4e",
          "radius": 0.35,
          "feather": 0.9
        },
        "softness": 0.16
      }
    },
    {
      "id": "look-018",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Papel marfil",
      "description": "Fotografía de estudio pastel, blancos de marfil y acabado mate.",
      "grade": {
        "curve": [
          0.07,
          0.33,
          0.64,
          0.87,
          0.97
        ],
        "saturation": 0.5,
        "temperature": 0.18,
        "hsl": {
          "blue": [
            -8,
            0.6,
            0.08
          ],
          "green": [
            -25,
            0.4,
            0.09
          ],
          "red": [
            5,
            0.75,
            0.04
          ]
        },
        "softness": 0.23,
        "vignette": {
          "amount": 0.23,
          "color": "#fff5e0",
          "radius": 0.6,
          "feather": 0.6
        },
        "grain": {
          "amount": 0.09,
          "size": 1.4,
          "color": 0.12
        }
      }
    },
    {
      "id": "look-019",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Instantánea roja",
      "description": "Pack instantáneo de rojos quemados, verdes desaturados y esquinas oscuras.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 0.78,
        "channels": [
          [
            0.09,
            0.35,
            0.63,
            0.91,
            1
          ],
          [
            0.015,
            0.16,
            0.35,
            0.66,
            0.91
          ],
          [
            0.035,
            0.17,
            0.38,
            0.68,
            0.94
          ]
        ],
        "hsl": {
          "green": [
            -18,
            0.3,
            -0.06
          ]
        },
        "grain": {
          "amount": 0.25,
          "size": 1.9,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.65,
          "color": "#f6d9ce",
          "radius": 0.55,
          "feather": 0.5
        },
        "halation": {
          "amount": 0.35,
          "radius": 0.018,
          "threshold": 0.6
        }
      }
    },
    {
      "id": "look-020",
      "category": "instant",
      "group": "Instantáneas y papel",
      "name": "Copia lavanda",
      "description": "Papel frío lavanda con suavidad óptica y esquinas blancas.",
      "grade": {
        "curve": [
          0.16,
          0.4,
          0.66,
          0.85,
          0.98
        ],
        "saturation": 0.65,
        "temperature": -0.1,
        "tint": 0.12,
        "split": {
          "shadows": "#7466a1",
          "highlights": "#e8ddfb",
          "amount": 0.4,
          "balance": 0.5
        },
        "softness": 0.34,
        "grain": {
          "amount": 0.11,
          "size": 2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.44,
          "color": "#ece6ff",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-021",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "E-6 en C-41",
      "description": "Diapositiva cruzada: sombras verdes, amarillos ácidos y contraste fuerte.",
      "grade": {
        "contrast": 1.16,
        "saturation": 1.35,
        "channels": [
          [
            0.015,
            0.13,
            0.53,
            0.94,
            1
          ],
          [
            0.12,
            0.47,
            0.75,
            0.94,
            1
          ],
          [
            0.16,
            0.33,
            0.48,
            0.65,
            0.82
          ]
        ],
        "grain": {
          "amount": 0.24,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.38,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        },
        "halation": {
          "amount": 0.18,
          "radius": 0.018,
          "threshold": 0.6
        }
      }
    },
    {
      "id": "look-022",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "C-41 en E-6",
      "description": "Negativo cruzado: sombras moradas y luces rosa intenso.",
      "grade": {
        "contrast": 1.14,
        "saturation": 1.24,
        "channels": [
          [
            0.09,
            0.26,
            0.61,
            0.91,
            1
          ],
          [
            0,
            0.1,
            0.34,
            0.65,
            0.83
          ],
          [
            0.21,
            0.45,
            0.7,
            0.88,
            1
          ]
        ],
        "grain": {
          "amount": 0.21,
          "size": 0.9,
          "color": 0.26
        },
        "vignette": {
          "amount": 0.3,
          "color": "#251040",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-023",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce turquesa y naranja",
      "description": "Sombras turquesa profundas que se vuelven naranja en las luces.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.15,
        "channels": [
          [
            0,
            0.09,
            0.52,
            0.92,
            1
          ],
          [
            0.15,
            0.39,
            0.6,
            0.78,
            0.95
          ],
          [
            0.25,
            0.5,
            0.65,
            0.71,
            0.74
          ]
        ],
        "grain": {
          "amount": 0.18,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.24,
          "color": "#062e37",
          "radius": 0.35,
          "feather": 0.9
        },
        "halation": {
          "amount": 0.33,
          "radius": 0.012,
          "threshold": 0.62
        }
      }
    },
    {
      "id": "look-024",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce magenta y lima",
      "description": "Canales invertidos en las zonas tonales: magenta abajo y lima arriba.",
      "grade": {
        "contrast": 1.08,
        "saturation": 1.1,
        "channels": [
          [
            0.17,
            0.35,
            0.56,
            0.78,
            0.93
          ],
          [
            0,
            0.13,
            0.52,
            0.92,
            1
          ],
          [
            0.22,
            0.44,
            0.5,
            0.58,
            0.7
          ]
        ],
        "grain": {
          "amount": 0.27,
          "size": 1.5,
          "color": 0.3
        },
        "vignette": {
          "amount": 0.36,
          "color": "#271029",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-025",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce limón eléctrico",
      "description": "Dominante amarilla de laboratorio, negros verdosos y azul comprimido.",
      "grade": {
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.45,
        "channels": [
          [
            0.04,
            0.35,
            0.73,
            0.96,
            1
          ],
          [
            0.12,
            0.4,
            0.67,
            0.9,
            1
          ],
          [
            0.01,
            0.1,
            0.25,
            0.46,
            0.67
          ]
        ],
        "hsl": {
          "green": [
            -18,
            1.25,
            0
          ]
        },
        "grain": {
          "amount": 0.16,
          "size": 0.8,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.45,
          "color": "#37320b",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-026",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce rojo y hielo",
      "description": "Sombras rojo oscuro, medios cálidos y blancos azulados.",
      "grade": {
        "contrast": 1.2,
        "saturation": 1.17,
        "channels": [
          [
            0.18,
            0.41,
            0.64,
            0.78,
            0.89
          ],
          [
            0.015,
            0.11,
            0.39,
            0.76,
            1
          ],
          [
            0.02,
            0.11,
            0.42,
            0.85,
            1
          ]
        ],
        "grain": {
          "amount": 0.3,
          "size": 1.3,
          "color": 0.12
        },
        "halation": {
          "amount": 0.42,
          "radius": 0.018,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.32,
          "color": "#340707",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-027",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce cobalto y cobre",
      "description": "Negros azul cobalto y luces cobrizas con curvas opuestas.",
      "grade": {
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "saturation": 1.32,
        "channels": [
          [
            0.02,
            0.15,
            0.56,
            0.91,
            1
          ],
          [
            0.03,
            0.14,
            0.39,
            0.7,
            0.88
          ],
          [
            0.31,
            0.48,
            0.56,
            0.59,
            0.62
          ]
        ],
        "grain": {
          "amount": 0.23,
          "size": 1.2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.37,
          "color": "#101049",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-028",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce esmeralda",
      "description": "Verde de diapositiva saturado, luces cian y azules densos.",
      "grade": {
        "contrast": 1.28,
        "saturation": 1.28,
        "channels": [
          [
            0,
            0.1,
            0.36,
            0.7,
            0.87
          ],
          [
            0.18,
            0.43,
            0.66,
            0.89,
            1
          ],
          [
            0.045,
            0.15,
            0.44,
            0.87,
            1
          ]
        ],
        "grain": {
          "amount": 0.2,
          "size": 0.9,
          "color": 0.15
        },
        "vignette": {
          "amount": 0.54,
          "color": "#052314",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-029",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce violeta solar",
      "description": "Sombras violeta, medios rojizos y luces amarillas quemadas.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.3,
        "channels": [
          [
            0.15,
            0.36,
            0.71,
            0.96,
            1
          ],
          [
            0.015,
            0.12,
            0.4,
            0.85,
            1
          ],
          [
            0.28,
            0.44,
            0.54,
            0.56,
            0.62
          ]
        ],
        "grain": {
          "amount": 0.26,
          "size": 1.3,
          "color": 0.12
        },
        "leaks": [
          {
            "color": "#ffb521",
            "amount": 0.4,
            "position": [
              1,
              0.15
            ],
            "radius": 0.55,
            "stretch": 1
          }
        ],
        "vignette": {
          "amount": 0.29,
          "color": "#2d1044",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-030",
      "category": "cross",
      "group": "Procesos cruzados",
      "name": "Cruce azul y fucsia",
      "description": "Sombras azul petróleo y luces fucsia en un revelado de contraste duro.",
      "grade": {
        "contrast": 1.12,
        "saturation": 1.45,
        "channels": [
          [
            0.025,
            0.18,
            0.53,
            0.9,
            1
          ],
          [
            0.12,
            0.33,
            0.4,
            0.51,
            0.7
          ],
          [
            0.23,
            0.44,
            0.6,
            0.9,
            1
          ]
        ],
        "grain": {
          "amount": 0.19,
          "size": 1,
          "color": 0.25
        },
        "vignette": {
          "amount": 0.36,
          "color": "#0a223d",
          "radius": 0.35,
          "feather": 0.9
        },
        "bloom": {
          "amount": 0.14,
          "radius": 0.02,
          "threshold": 0.65
        }
      }
    },
    {
      "id": "look-031",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "35 mm ámbar",
      "description": "Negativo cinematográfico cálido, azules verdosos y halation rojo.",
      "grade": {
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "saturation": 0.84,
        "temperature": 0.14,
        "hsl": {
          "blue": [
            -24,
            0.85,
            -0.03
          ],
          "green": [
            -10,
            0.7,
            -0.02
          ]
        },
        "split": {
          "shadows": "#345b67",
          "highlights": "#e7ac59",
          "amount": 0.25,
          "balance": 0.5
        },
        "grain": {
          "amount": 0.24,
          "size": 1.1,
          "color": 0.12
        },
        "halation": {
          "amount": 0.6,
          "radius": 0.012,
          "threshold": 0.64
        },
        "vignette": {
          "amount": 0.24,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-032",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Teal de gran pantalla",
      "description": "Sombras cian marcadas y piel cálida conservada por selección de color.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.08,
        "hsl": {
          "blue": [
            -30,
            1.2,
            -0.06
          ],
          "cyan": [
            -10,
            1.05,
            -0.03
          ],
          "orange": [
            -6,
            1.08,
            0.04
          ],
          "green": [
            -20,
            0.5,
            -0.05
          ]
        },
        "split": {
          "shadows": "#14576b",
          "highlights": "#f4bb72",
          "amount": 0.4,
          "balance": 0.58
        },
        "grain": {
          "amount": 0.12,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.26,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-033",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Bleach bypass",
      "description": "Retención de plata: color reducido, negros duros y contraste metálico.",
      "grade": {
        "curve": [
          0.01,
          0.15,
          0.46,
          0.88,
          1
        ],
        "saturation": 0.38,
        "bleach": 0.82,
        "temperature": -0.06,
        "grain": {
          "amount": 0.29,
          "size": 1,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.23,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-034",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Technicolor tres tiras",
      "description": "Rojo intenso, cielos cian y verdes separados de la piel.",
      "grade": {
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.3,
        "hsl": {
          "red": [
            -4,
            1.35,
            -0.02
          ],
          "green": [
            12,
            1.35,
            -0.06
          ],
          "blue": [
            -28,
            1.3,
            0.03
          ],
          "yellow": [
            -12,
            0.9,
            0.02
          ]
        },
        "channels": [
          [
            0.02,
            0.23,
            0.57,
            0.86,
            1
          ],
          [
            0,
            0.19,
            0.49,
            0.8,
            0.99
          ],
          [
            0.02,
            0.24,
            0.47,
            0.77,
            0.96
          ]
        ],
        "grain": {
          "amount": 0.1,
          "size": 0.8,
          "color": 0.12
        }
      }
    },
    {
      "id": "look-035",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Western de desierto",
      "description": "Ocres secos, cielo turquesa apagado y polvo de película.",
      "grade": {
        "curve": [
          0.055,
          0.18,
          0.43,
          0.73,
          0.94
        ],
        "saturation": 0.6,
        "temperature": 0.4,
        "hsl": {
          "blue": [
            -20,
            0.65,
            -0.08
          ],
          "green": [
            -42,
            0.4,
            -0.04
          ]
        },
        "split": {
          "shadows": "#65553d",
          "highlights": "#ecc684",
          "amount": 0.26,
          "balance": 0.5
        },
        "grain": {
          "amount": 0.32,
          "size": 1.8,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.42,
          "color": "#493222",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-036",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Thriller de acero",
      "description": "Paleta fría casi desaturada, verdes azulados y negros profundos.",
      "grade": {
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "saturation": 0.46,
        "temperature": -0.28,
        "hsl": {
          "green": [
            35,
            0.65,
            -0.04
          ],
          "blue": [
            -8,
            0.8,
            -0.1
          ]
        },
        "split": {
          "shadows": "#1a414d",
          "highlights": "#a2c7d5",
          "amount": 0.3,
          "balance": 0.5
        },
        "grain": {
          "amount": 0.18,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.47,
          "color": "#09191f",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-037",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Romance de celuloide",
      "description": "Luces melocotón, sombras ciruela y difusión sobre los brillos.",
      "grade": {
        "curve": [
          0.035,
          0.29,
          0.58,
          0.8,
          0.97
        ],
        "saturation": 0.7,
        "temperature": 0.17,
        "split": {
          "shadows": "#704960",
          "highlights": "#ffd6a7",
          "amount": 0.28,
          "balance": 0.5
        },
        "softness": 0.22,
        "bloom": {
          "amount": 0.44,
          "radius": 0.022,
          "threshold": 0.48
        },
        "grain": {
          "amount": 0.16,
          "size": 1.6,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.17,
          "color": "#4b2d3b",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-038",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Distopía verdosa",
      "description": "Negros verdes, piel pálida y blancos verdosos de cine distópico.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 0.52,
        "channels": [
          [
            0.01,
            0.17,
            0.4,
            0.69,
            0.91
          ],
          [
            0.055,
            0.3,
            0.61,
            0.86,
            1
          ],
          [
            0.02,
            0.17,
            0.4,
            0.64,
            0.86
          ]
        ],
        "grain": {
          "amount": 0.27,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.5,
          "color": "#071d0c",
          "radius": 0.35,
          "feather": 0.9
        },
        "bleach": 0.23
      }
    },
    {
      "id": "look-039",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Épica dorada",
      "description": "Oro en altas luces, sombras azul oscuro y contraste de pantalla grande.",
      "grade": {
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.15,
        "channels": [
          [
            0.04,
            0.29,
            0.66,
            0.9,
            1
          ],
          [
            0.015,
            0.21,
            0.47,
            0.76,
            0.95
          ],
          [
            0.12,
            0.31,
            0.47,
            0.61,
            0.78
          ]
        ],
        "split": {
          "shadows": "#292557",
          "highlights": "#ffca3a",
          "amount": 0.52,
          "balance": 0.62
        },
        "hsl": {
          "green": [
            -16,
            0.65,
            0
          ]
        },
        "grain": {
          "amount": 0.13,
          "size": 1.3,
          "color": 0.12
        },
        "halation": {
          "amount": 0.24,
          "radius": 0.018,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.27,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-040",
      "category": "cinema",
      "group": "Cine y revelado",
      "name": "Cine francés mate",
      "description": "Negros lavados de verde, rojo apagado y blancos de papel suave.",
      "grade": {
        "curve": [
          0.095,
          0.27,
          0.48,
          0.7,
          0.93
        ],
        "saturation": 0.6,
        "channels": [
          [
            0.04,
            0.24,
            0.5,
            0.78,
            0.97
          ],
          [
            0.09,
            0.29,
            0.52,
            0.76,
            0.94
          ],
          [
            0.09,
            0.25,
            0.45,
            0.68,
            0.88
          ]
        ],
        "grain": {
          "amount": 0.23,
          "size": 1.7,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.22,
          "color": "#26352c",
          "radius": 0.35,
          "feather": 0.9
        },
        "softness": 0.1
      }
    },
    {
      "id": "look-041",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Sepia de archivo",
      "description": "Virado sepia completo: marrón profundo, medios de cobre y papel crema.",
      "grade": {
        "mono": true,
        "curve": [
          0.03,
          0.18,
          0.45,
          0.75,
          0.96
        ],
        "duotone": [
          "#281508",
          "#9b6c3c",
          "#f9e7bd"
        ],
        "grain": {
          "amount": 0.25,
          "size": 1.8,
          "color": 0
        },
        "vignette": {
          "amount": 0.4,
          "color": "#4b2c17",
          "radius": 0.35,
          "feather": 0.9
        },
        "dust": {
          "count": 20,
          "amount": 0.35,
          "size": 2
        }
      }
    },
    {
      "id": "look-042",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Cianotipia azul Prusia",
      "description": "Impresión azul de Prusia con blancos de papel y medios cian.",
      "grade": {
        "mono": true,
        "monoMix": [
          0.18,
          0.62,
          0.2
        ],
        "contrast": 1.1,
        "duotone": [
          "#071b4f",
          "#246aa2",
          "#edf9ff"
        ],
        "toneGamma": 1.12,
        "grain": {
          "amount": 0.17,
          "size": 1.9,
          "color": 0
        },
        "vignette": {
          "amount": 0.25,
          "color": "#f3f7f0",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-043",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Selenio ciruela",
      "description": "Plata virada al selenio: negros morados y medios malva.",
      "grade": {
        "mono": true,
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "duotone": [
          "#1f0c25",
          "#94708b",
          "#f6e4f0"
        ],
        "grain": {
          "amount": 0.2,
          "size": 1.1,
          "color": 0
        },
        "vignette": {
          "amount": 0.22,
          "color": "#251021",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-044",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Cobre de laboratorio",
      "description": "Negros rojizos, medios de cobre intenso y luces rosadas.",
      "grade": {
        "mono": true,
        "curve": [
          0.015,
          0.12,
          0.4,
          0.74,
          0.98
        ],
        "duotone": [
          "#440509",
          "#a74124",
          "#ffc49a"
        ],
        "toneGamma": 1.05,
        "grain": {
          "amount": 0.21,
          "size": 1.4,
          "color": 0
        },
        "vignette": {
          "amount": 0.29,
          "color": "#3b160c",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-045",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Platinotipia",
      "description": "Tonos grises cálidos de platino y blanco mate sin brillo.",
      "grade": {
        "mono": true,
        "curve": [
          0.085,
          0.28,
          0.52,
          0.76,
          0.92
        ],
        "duotone": [
          "#38332e",
          "#9b968b",
          "#e8e2d3"
        ],
        "grain": {
          "amount": 0.12,
          "size": 1.5,
          "color": 0
        },
        "softness": 0.1,
        "vignette": {
          "amount": 0.16,
          "color": "#ece7dd",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-046",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Virado verde botella",
      "description": "Imagen monocroma verde con negros de botella y papel menta.",
      "grade": {
        "mono": true,
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "duotone": [
          "#061e12",
          "#44886b",
          "#e0f3d6"
        ],
        "grain": {
          "amount": 0.18,
          "size": 1.2,
          "color": 0
        },
        "vignette": {
          "amount": 0.3,
          "color": "#092a1b",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-047",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Oro sobre plata",
      "description": "Virado dorado con sombras de carbón y luces ámbar.",
      "grade": {
        "mono": true,
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "duotone": [
          "#241e10",
          "#b39943",
          "#fff2b8"
        ],
        "grain": {
          "amount": 0.14,
          "size": 1,
          "color": 0
        },
        "bloom": {
          "amount": 0.13,
          "radius": 0.018,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.2,
          "color": "#362b12",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-048",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Manganeso berenjena",
      "description": "Virado intenso berenjena con medios morados y altas luces lila.",
      "grade": {
        "mono": true,
        "curve": [
          0.01,
          0.14,
          0.39,
          0.7,
          0.93
        ],
        "duotone": [
          "#230c39",
          "#865fa8",
          "#f0dcff"
        ],
        "toneGamma": 1.18,
        "grain": {
          "amount": 0.26,
          "size": 1.8,
          "color": 0
        },
        "vignette": {
          "amount": 0.38,
          "color": "#1b0829",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-049",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Hierro rojo",
      "description": "Virado rojo óxido de contraste alto sobre papel rosado.",
      "grade": {
        "mono": true,
        "contrast": 1.1,
        "duotone": [
          "#390b08",
          "#bd4930",
          "#ffe3c7"
        ],
        "grain": {
          "amount": 0.23,
          "size": 1.6,
          "color": 0
        },
        "vignette": {
          "amount": 0.24,
          "color": "#652316",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-050",
      "category": "toned",
      "group": "Virados y técnicas históricas",
      "name": "Duotono petróleo y salmón",
      "description": "Dos tintas visibles: petróleo en sombras y salmón en luces.",
      "grade": {
        "mono": true,
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "duotone": [
          "#083c47",
          "#8b9e98",
          "#ffc8a5"
        ],
        "grain": {
          "amount": 0.1,
          "size": 1.2,
          "color": 0
        },
        "vignette": {
          "amount": 0.23,
          "color": "#153e43",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-051",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Plata documental",
      "description": "Blanco y negro clásico, grano de 35 mm y medios naturales.",
      "grade": {
        "mono": true,
        "curve": [
          0.02,
          0.19,
          0.49,
          0.8,
          0.98
        ],
        "grain": {
          "amount": 0.25,
          "size": 1,
          "color": 0
        },
        "vignette": {
          "amount": 0.2,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-052",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Tri-X de calle",
      "description": "Negros compactos, blancos duros y grano documental grueso.",
      "grade": {
        "mono": true,
        "curve": [
          0,
          0.08,
          0.39,
          0.88,
          1
        ],
        "contrast": 1.12,
        "grain": {
          "amount": 0.48,
          "size": 1.8,
          "color": 0
        },
        "vignette": {
          "amount": 0.4,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-053",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Filtro rojo de paisaje",
      "description": "Cielos azules muy oscuros y piel clara mediante mezcla roja.",
      "grade": {
        "mono": true,
        "monoMix": [
          0.82,
          0.15,
          0.03
        ],
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "grain": {
          "amount": 0.12,
          "size": 1,
          "color": 0
        },
        "vignette": {
          "amount": 0.25,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-054",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Filtro azul ortocromático",
      "description": "Rojos y piel oscuros, cielos claros y carácter ortocromático.",
      "grade": {
        "mono": true,
        "monoMix": [
          0.02,
          0.3,
          0.68
        ],
        "curve": [
          0.015,
          0.14,
          0.45,
          0.81,
          0.97
        ],
        "grain": {
          "amount": 0.3,
          "size": 1.5,
          "color": 0
        },
        "vignette": {
          "amount": 0.27,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-055",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "High key de estudio",
      "description": "Blanco y negro luminoso, negros suaves y viñeta blanca.",
      "grade": {
        "mono": true,
        "exposure": 0.4,
        "curve": [
          0.075,
          0.37,
          0.7,
          0.91,
          1
        ],
        "grain": {
          "amount": 0.06,
          "size": 1,
          "color": 0
        },
        "vignette": {
          "amount": 0.48,
          "color": "#ffffff",
          "radius": 0.45,
          "feather": 0.8
        },
        "softness": 0.08
      }
    },
    {
      "id": "look-056",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Low key noir",
      "description": "Sombras dominantes, altas luces aisladas y bordes oscuros.",
      "grade": {
        "mono": true,
        "exposure": -0.32,
        "curve": [
          0,
          0.05,
          0.25,
          0.63,
          0.97
        ],
        "grain": {
          "amount": 0.2,
          "size": 1.2,
          "color": 0
        },
        "vignette": {
          "amount": 0.7,
          "color": "#000000",
          "radius": 0.25,
          "feather": 0.75
        }
      }
    },
    {
      "id": "look-057",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Carbón mate",
      "description": "Negros lavados, blanco gris y textura de copia al carbón.",
      "grade": {
        "mono": true,
        "curve": [
          0.16,
          0.29,
          0.46,
          0.66,
          0.83
        ],
        "grain": {
          "amount": 0.28,
          "size": 2,
          "color": 0
        },
        "vignette": {
          "amount": 0.18,
          "color": "#403b35",
          "radius": 0.35,
          "feather": 0.9
        },
        "softness": 0.15
      }
    },
    {
      "id": "look-058",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Lith de cuarto oscuro",
      "description": "Revelado lith de sombras duras y luces suaves con grano irregular.",
      "grade": {
        "mono": true,
        "curve": [
          0.01,
          0.045,
          0.31,
          0.86,
          0.96
        ],
        "duotone": [
          "#100e0c",
          "#a89b89",
          "#efddbe"
        ],
        "grain": {
          "amount": 0.65,
          "size": 2.6,
          "color": 0
        },
        "vignette": {
          "amount": 0.35,
          "color": "#282119",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-059",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Infrarrojo de plata",
      "description": "Follaje blanco, cielo oscuro y difusión infrarroja simulada.",
      "grade": {
        "mono": true,
        "monoMix": [
          0.12,
          1.03,
          -0.15
        ],
        "curve": [
          0.015,
          0.19,
          0.53,
          0.88,
          1
        ],
        "bloom": {
          "amount": 0.6,
          "radius": 0.018,
          "threshold": 0.4
        },
        "softness": 0.18,
        "grain": {
          "amount": 0.13,
          "size": 1.6,
          "color": 0
        },
        "vignette": {
          "amount": 0.2,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-060",
      "category": "mono",
      "group": "Blanco y negro",
      "name": "Plata fría de noche",
      "description": "Negros de azul acero, brillo plateado y luz difusa.",
      "grade": {
        "mono": true,
        "curve": [
          0,
          0.085,
          0.34,
          0.74,
          0.98
        ],
        "duotone": [
          "#070d17",
          "#6d8196",
          "#e8f5ff"
        ],
        "bloom": {
          "amount": 0.35,
          "radius": 0.017,
          "threshold": 0.65
        },
        "grain": {
          "amount": 0.31,
          "size": 1.3,
          "color": 0
        },
        "vignette": {
          "amount": 0.3,
          "color": "#060a12",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-061",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Lomo LC-A",
      "description": "Contraste Lomo, color saturado y viñeta negra muy marcada.",
      "grade": {
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.55,
        "hsl": {
          "blue": [
            -18,
            1.2,
            -0.04
          ],
          "green": [
            -20,
            1.1,
            -0.05
          ]
        },
        "grain": {
          "amount": 0.22,
          "size": 1.1,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.84,
          "color": "#000000",
          "radius": 0.18,
          "feather": 0.85
        }
      }
    },
    {
      "id": "look-062",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Holga de plástico",
      "description": "Óptica blanda, color cálido, fuga naranja y esquinas de cámara de juguete.",
      "grade": {
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "saturation": 0.83,
        "temperature": 0.2,
        "softness": 0.5,
        "grain": {
          "amount": 0.34,
          "size": 2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.73,
          "color": "#23140c",
          "radius": 0.15,
          "feather": 0.85
        },
        "aberration": 3.5,
        "leaks": [
          {
            "color": "#ff6419",
            "amount": 0.7,
            "position": [
              0,
              0.8
            ],
            "radius": 0.65,
            "stretch": 0.3
          }
        ]
      }
    },
    {
      "id": "look-063",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Diana de ensueño",
      "description": "Azul de juguete, difusión visible y veladura clara en las esquinas.",
      "grade": {
        "curve": [
          0.1,
          0.32,
          0.54,
          0.78,
          0.97
        ],
        "saturation": 0.77,
        "temperature": -0.3,
        "tint": 0.1,
        "softness": 0.65,
        "bloom": {
          "amount": 0.4,
          "radius": 0.025,
          "threshold": 0.5
        },
        "grain": {
          "amount": 0.18,
          "size": 1.7,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.44,
          "color": "#d5eaff",
          "radius": 0.35,
          "feather": 0.9
        },
        "aberration": 2
      }
    },
    {
      "id": "look-064",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Redscale rojo",
      "description": "Película expuesta por detrás: rojos, naranja y casi ningún azul.",
      "grade": {
        "saturation": 0.95,
        "channels": [
          [
            0.14,
            0.42,
            0.69,
            0.94,
            1
          ],
          [
            0.015,
            0.14,
            0.29,
            0.48,
            0.71
          ],
          [
            0,
            0.015,
            0.045,
            0.12,
            0.27
          ]
        ],
        "grain": {
          "amount": 0.28,
          "size": 1.4,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.5,
          "color": "#300b02",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-065",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Redscale ámbar",
      "description": "Versión ámbar de redscale con oro intenso y sombras marrones.",
      "grade": {
        "exposure": 0.2,
        "channels": [
          [
            0.1,
            0.4,
            0.65,
            0.88,
            1
          ],
          [
            0.05,
            0.28,
            0.49,
            0.73,
            0.94
          ],
          [
            0.005,
            0.06,
            0.14,
            0.27,
            0.45
          ]
        ],
        "saturation": 0.68,
        "grain": {
          "amount": 0.24,
          "size": 1.6,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.39,
          "color": "#432509",
          "radius": 0.35,
          "feather": 0.9
        },
        "halation": {
          "amount": 0.3,
          "radius": 0.018,
          "threshold": 0.6
        }
      }
    },
    {
      "id": "look-066",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Esténopo sepia",
      "description": "Luz de cámara estenopeica, desenfoque suave y bordes sepia oscuros.",
      "grade": {
        "mono": true,
        "curve": [
          0.035,
          0.29,
          0.58,
          0.8,
          0.97
        ],
        "duotone": [
          "#1d1710",
          "#9b8870",
          "#f0e5ca"
        ],
        "softness": 0.72,
        "vignette": {
          "amount": 0.78,
          "color": "#140e08",
          "radius": 0.08,
          "feather": 0.9
        },
        "grain": {
          "amount": 0.2,
          "size": 2,
          "color": 0
        }
      }
    },
    {
      "id": "look-067",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Compacta CCD 2003",
      "description": "Azul eléctrico, piel magenta, negros digitales y ruido de color.",
      "grade": {
        "curve": [
          0,
          0.2,
          0.51,
          0.87,
          1
        ],
        "saturation": 1.45,
        "hsl": {
          "blue": [
            9,
            1.3,
            -0.04
          ],
          "red": [
            -12,
            1.2,
            0
          ]
        },
        "tint": 0.1,
        "grain": {
          "amount": 0.25,
          "size": 0.65,
          "color": 0.9
        },
        "aberration": 1.4,
        "vignette": {
          "amount": 0.14,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-068",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Ojo de pez cromático",
      "description": "Esquinas densas y separación de canales de una lente económica.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.3,
        "temperature": -0.12,
        "aberration": 7,
        "vignette": {
          "amount": 0.85,
          "color": "#020811",
          "radius": 0.12,
          "feather": 0.76
        },
        "grain": {
          "amount": 0.17,
          "size": 0.9,
          "color": 0.12
        }
      }
    },
    {
      "id": "look-069",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Fuga de luz doble",
      "description": "Veladuras roja y azul en lados opuestos sobre negativo suave.",
      "grade": {
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "saturation": 0.75,
        "grain": {
          "amount": 0.27,
          "size": 1.5,
          "color": 0.12
        },
        "leaks": [
          {
            "color": "#ff3710",
            "amount": 0.95,
            "position": [
              0,
              0.32
            ],
            "radius": 0.82,
            "stretch": 0.35
          },
          {
            "color": "#496dff",
            "amount": 0.85,
            "position": [
              1,
              0.72
            ],
            "radius": 0.68,
            "stretch": 0.45
          }
        ],
        "vignette": {
          "amount": 0.3,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-070",
      "category": "camera",
      "group": "Lomo y cámaras",
      "name": "Medio formato otoñal",
      "description": "Negativo de cámara clásica, colores tierra, detalle suave y luz cálida.",
      "grade": {
        "curve": [
          0.02,
          0.24,
          0.54,
          0.78,
          0.95
        ],
        "saturation": 0.85,
        "hsl": {
          "green": [
            -35,
            0.65,
            -0.02
          ],
          "yellow": [
            -15,
            0.95,
            0.02
          ],
          "blue": [
            -15,
            0.75,
            -0.03
          ]
        },
        "temperature": 0.15,
        "grain": {
          "amount": 0.14,
          "size": 0.8,
          "color": 0.12
        },
        "halation": {
          "amount": 0.18,
          "radius": 0.007,
          "threshold": 0.7
        },
        "vignette": {
          "amount": 0.26,
          "color": "#251f16",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-071",
      "category": "color",
      "group": "Color creativo",
      "name": "Editorial nítido",
      "description": "Color limpio de alto contraste, piel viva y negros neutros.",
      "grade": {
        "curve": [
          0.01,
          0.2,
          0.53,
          0.83,
          1
        ],
        "saturation": 1.08,
        "hsl": {
          "orange": [
            -3,
            1.03,
            0.035
          ],
          "blue": [
            -5,
            1.1,
            -0.02
          ]
        },
        "vignette": {
          "amount": 0.1,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-072",
      "category": "color",
      "group": "Color creativo",
      "name": "Pastel de algodón",
      "description": "Contraste bajo, colores pastel y luz blanca suave.",
      "grade": {
        "curve": [
          0.15,
          0.4,
          0.66,
          0.84,
          0.99
        ],
        "saturation": 0.5,
        "exposure": 0.15,
        "hsl": {
          "blue": [
            -18,
            0.8,
            0.05
          ],
          "green": [
            -20,
            0.7,
            0.06
          ]
        },
        "bloom": {
          "amount": 0.17,
          "radius": 0.02,
          "threshold": 0.6
        },
        "vignette": {
          "amount": 0.18,
          "color": "#fff9f2",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-073",
      "category": "color",
      "group": "Color creativo",
      "name": "Bosque esmeralda",
      "description": "Verdes esmeralda, azules oscuros y medios luminosos.",
      "grade": {
        "curve": [
          0.025,
          0.23,
          0.51,
          0.83,
          0.99
        ],
        "contrast": 1.05,
        "saturation": 1.32,
        "hsl": {
          "green": [
            25,
            1.65,
            -0.04
          ],
          "yellow": [
            35,
            1.2,
            0
          ],
          "blue": [
            -5,
            0.85,
            -0.12
          ]
        },
        "split": {
          "shadows": "#164f3a",
          "highlights": "#cfe1af",
          "amount": 0.22,
          "balance": 0.5
        },
        "vignette": {
          "amount": 0.2,
          "color": "#062317",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-074",
      "category": "color",
      "group": "Color creativo",
      "name": "Otoño de cobre",
      "description": "Verde convertido en tierra y naranja de otoño con sombras cálidas.",
      "grade": {
        "curve": [
          0.085,
          0.23,
          0.48,
          0.73,
          0.955
        ],
        "saturation": 1.12,
        "hsl": {
          "green": [
            -70,
            0.7,
            -0.04
          ],
          "yellow": [
            -28,
            1.35,
            0
          ],
          "orange": [
            -8,
            1.2,
            0
          ],
          "blue": [
            -10,
            0.4,
            -0.03
          ]
        },
        "temperature": 0.22,
        "split": {
          "shadows": "#633b23",
          "highlights": "#e8b568",
          "amount": 0.24,
          "balance": 0.5
        },
        "vignette": {
          "amount": 0.24,
          "color": "#342011",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-075",
      "category": "color",
      "group": "Color creativo",
      "name": "Océano profundo",
      "description": "Azules y cian intensos con rojos atenuados y sombras marinas.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.1,
        "temperature": -0.2,
        "hsl": {
          "blue": [
            -18,
            1.5,
            -0.1
          ],
          "cyan": [
            -8,
            1.3,
            -0.04
          ],
          "red": [
            0,
            0.65,
            0
          ]
        },
        "split": {
          "shadows": "#143c70",
          "highlights": "#b5f2ed",
          "amount": 0.3,
          "balance": 0.5
        },
        "vignette": {
          "amount": 0.29,
          "color": "#09233d",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-076",
      "category": "color",
      "group": "Color creativo",
      "name": "Melocotón y salvia",
      "description": "Piel melocotón, verdes salvia y azul desaturado en un acabado mate.",
      "grade": {
        "curve": [
          0.06,
          0.3,
          0.6,
          0.82,
          0.97
        ],
        "saturation": 0.68,
        "hsl": {
          "orange": [
            -8,
            1.15,
            0.065
          ],
          "green": [
            -18,
            0.4,
            0.09
          ],
          "blue": [
            -20,
            0.4,
            0.08
          ]
        },
        "split": {
          "shadows": "#566754",
          "highlights": "#ffd1b0",
          "amount": 0.24,
          "balance": 0.5
        },
        "softness": 0.08
      }
    },
    {
      "id": "look-077",
      "category": "color",
      "group": "Color creativo",
      "name": "Flor de cerezo",
      "description": "Rosas luminosos, verdes apagados y luces de primavera.",
      "grade": {
        "curve": [
          0.035,
          0.29,
          0.58,
          0.8,
          0.97
        ],
        "saturation": 0.83,
        "tint": 0.12,
        "hsl": {
          "red": [
            -18,
            1.3,
            0.05
          ],
          "magenta": [
            10,
            1.2,
            0.045
          ],
          "green": [
            -6,
            0.45,
            0.04
          ]
        },
        "split": {
          "shadows": "#63506c",
          "highlights": "#ffe0ec",
          "amount": 0.2,
          "balance": 0.5
        },
        "bloom": {
          "amount": 0.23,
          "radius": 0.02,
          "threshold": 0.55
        },
        "vignette": {
          "amount": 0.24,
          "color": "#fde7ef",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-078",
      "category": "color",
      "group": "Color creativo",
      "name": "Cromo de moda",
      "description": "Color muy saturado, negros fríos y separación dura de los tonos.",
      "grade": {
        "contrast": 1.13,
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.6,
        "channels": [
          [
            0.02,
            0.23,
            0.52,
            0.85,
            1
          ],
          [
            0,
            0.17,
            0.46,
            0.82,
            0.98
          ],
          [
            0.08,
            0.26,
            0.56,
            0.9,
            1
          ]
        ],
        "vignette": {
          "amount": 0.16,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-079",
      "category": "color",
      "group": "Color creativo",
      "name": "Desaturación selectiva roja",
      "description": "Solo rojos y piel mantienen color; el resto cae hacia gris.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1,
        "hsl": {
          "yellow": [
            0,
            0,
            0
          ],
          "green": [
            0,
            0,
            0
          ],
          "cyan": [
            0,
            0,
            0
          ],
          "blue": [
            0,
            0,
            0
          ],
          "purple": [
            0,
            0,
            0
          ],
          "magenta": [
            0,
            0.12,
            0
          ],
          "red": [
            0,
            1.2,
            0
          ]
        },
        "vignette": {
          "amount": 0.24,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-080",
      "category": "color",
      "group": "Color creativo",
      "name": "Verano mediterráneo",
      "description": "Luz cálida, cielos turquesa, blanco brillante y amarillo solar.",
      "grade": {
        "exposure": 0.17,
        "curve": [
          0.015,
          0.28,
          0.61,
          0.87,
          1
        ],
        "saturation": 1.2,
        "temperature": 0.14,
        "hsl": {
          "blue": [
            -26,
            1.3,
            0.02
          ],
          "cyan": [
            -12,
            1.15,
            0.01
          ],
          "yellow": [
            -8,
            1.08,
            0.04
          ],
          "green": [
            -18,
            0.8,
            0.02
          ]
        },
        "halation": {
          "amount": 0.13,
          "radius": 0.006,
          "threshold": 0.78
        }
      }
    },
    {
      "id": "look-081",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Neón Tokio",
      "description": "Azules eléctricos, luces magenta y halos de neón.",
      "grade": {
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "saturation": 1.55,
        "hsl": {
          "blue": [
            10,
            1.2,
            -0.06
          ],
          "magenta": [
            -12,
            1.3,
            0.04
          ],
          "yellow": [
            -30,
            0.6,
            0
          ]
        },
        "split": {
          "shadows": "#183770",
          "highlights": "#fa58c6",
          "amount": 0.4,
          "balance": 0.5
        },
        "bloom": {
          "amount": 0.5,
          "radius": 0.023,
          "threshold": 0.45
        },
        "halation": {
          "amount": 0.25,
          "radius": 0.018,
          "threshold": 0.6
        },
        "grain": {
          "amount": 0.15,
          "size": 1,
          "color": 0.35
        },
        "vignette": {
          "amount": 0.4,
          "color": "#080a20",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-082",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Tungsteno 800",
      "description": "Película nocturna: sombras cian, lámparas cálidas y halo rojo visible.",
      "grade": {
        "curve": [
          0.015,
          0.12,
          0.36,
          0.72,
          0.98
        ],
        "temperature": -0.23,
        "saturation": 1.1,
        "split": {
          "shadows": "#236a7b",
          "highlights": "#ffbc71",
          "amount": 0.3,
          "balance": 0.5
        },
        "halation": {
          "amount": 0.95,
          "radius": 0.022,
          "threshold": 0.48
        },
        "bloom": {
          "amount": 0.26,
          "radius": 0.012,
          "threshold": 0.64
        },
        "grain": {
          "amount": 0.38,
          "size": 1.2,
          "color": 0.25
        },
        "vignette": {
          "amount": 0.24,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-083",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Farolas de sodio",
      "description": "Ámbar de alumbrado antiguo, sombras marrones y halos amplios.",
      "grade": {
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "saturation": 0.6,
        "temperature": 0.65,
        "channels": [
          [
            0.055,
            0.25,
            0.54,
            0.85,
            1
          ],
          [
            0.025,
            0.16,
            0.38,
            0.65,
            0.9
          ],
          [
            0,
            0.06,
            0.16,
            0.38,
            0.64
          ]
        ],
        "bloom": {
          "amount": 0.65,
          "radius": 0.035,
          "threshold": 0.4
        },
        "grain": {
          "amount": 0.3,
          "size": 1.4,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.37,
          "color": "#251507",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-084",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Mercurio verde",
      "description": "Luces verdes de mercurio sobre sombras frías casi negras.",
      "grade": {
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "saturation": 0.88,
        "channels": [
          [
            0,
            0.08,
            0.29,
            0.61,
            0.88
          ],
          [
            0.045,
            0.24,
            0.52,
            0.84,
            1
          ],
          [
            0.055,
            0.24,
            0.49,
            0.75,
            0.91
          ]
        ],
        "bloom": {
          "amount": 0.4,
          "radius": 0.025,
          "threshold": 0.5
        },
        "grain": {
          "amount": 0.3,
          "size": 1.3,
          "color": 0.3
        },
        "vignette": {
          "amount": 0.43,
          "color": "#031d1a",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-085",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Hora azul índigo",
      "description": "Azul de anochecer profundo con luces doradas preservadas.",
      "grade": {
        "curve": [
          0.1,
          0.3,
          0.55,
          0.8,
          0.97
        ],
        "saturation": 0.7,
        "split": {
          "shadows": "#34318f",
          "highlights": "#eed5a6",
          "amount": 0.45,
          "balance": 0.7
        },
        "hsl": {
          "blue": [
            12,
            1.15,
            -0.06
          ]
        },
        "grain": {
          "amount": 0.16,
          "size": 1.2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.3,
          "color": "#131337",
          "radius": 0.35,
          "feather": 0.9
        },
        "bloom": {
          "amount": 0.18,
          "radius": 0.018,
          "threshold": 0.6
        }
      }
    },
    {
      "id": "look-086",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Cyber rojo",
      "description": "Rojos y fucsia dominantes sobre un fondo azul petróleo.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.3,
        "channels": [
          [
            0.03,
            0.2,
            0.59,
            0.95,
            1
          ],
          [
            0.03,
            0.13,
            0.27,
            0.54,
            0.79
          ],
          [
            0.16,
            0.28,
            0.45,
            0.79,
            0.97
          ]
        ],
        "split": {
          "shadows": "#183d58",
          "highlights": "#ff4560",
          "amount": 0.3,
          "balance": 0.5
        },
        "bloom": {
          "amount": 0.4,
          "radius": 0.022,
          "threshold": 0.48
        },
        "aberration": 2.5,
        "grain": {
          "amount": 0.19,
          "size": 0.9,
          "color": 0.4
        },
        "vignette": {
          "amount": 0.4,
          "color": "#090e19",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-087",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Discoteca violeta",
      "description": "Sombras púrpuras, piel rosada y luz violeta difusa.",
      "grade": {
        "curve": [
          0.025,
          0.14,
          0.39,
          0.75,
          0.98
        ],
        "saturation": 1.28,
        "tint": 0.2,
        "channels": [
          [
            0.09,
            0.3,
            0.59,
            0.85,
            1
          ],
          [
            0,
            0.1,
            0.29,
            0.61,
            0.84
          ],
          [
            0.2,
            0.4,
            0.66,
            0.92,
            1
          ]
        ],
        "bloom": {
          "amount": 0.65,
          "radius": 0.03,
          "threshold": 0.38
        },
        "grain": {
          "amount": 0.22,
          "size": 1.2,
          "color": 0.3
        },
        "vignette": {
          "amount": 0.32,
          "color": "#20082d",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-088",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Luna de hielo",
      "description": "Noche azul casi monocroma, blancos fríos y bordes oscuros.",
      "grade": {
        "mono": true,
        "exposure": -0.2,
        "curve": [
          0.005,
          0.12,
          0.35,
          0.68,
          0.95
        ],
        "duotone": [
          "#020f2b",
          "#305898",
          "#bce9ff"
        ],
        "bloom": {
          "amount": 0.32,
          "radius": 0.02,
          "threshold": 0.58
        },
        "grain": {
          "amount": 0.18,
          "size": 1.2,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.56,
          "color": "#071423",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-089",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Bar de jazz",
      "description": "Sombras marrón rojizo, luces de tabaco y grano de alta sensibilidad.",
      "grade": {
        "curve": [
          0.01,
          0.095,
          0.29,
          0.63,
          0.92
        ],
        "saturation": 0.65,
        "temperature": 0.3,
        "split": {
          "shadows": "#562d29",
          "highlights": "#efad64",
          "amount": 0.3,
          "balance": 0.5
        },
        "grain": {
          "amount": 0.55,
          "size": 1.5,
          "color": 0.15
        },
        "halation": {
          "amount": 0.55,
          "radius": 0.014,
          "threshold": 0.5
        },
        "vignette": {
          "amount": 0.5,
          "color": "#1b0c07",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-090",
      "category": "night",
      "group": "Nocturnos y neón",
      "name": "Neón verde y naranja",
      "description": "Neón verde, brillos naranja y medios de alto contraste.",
      "grade": {
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "saturation": 1.4,
        "channels": [
          [
            0.01,
            0.12,
            0.44,
            0.89,
            1
          ],
          [
            0.12,
            0.38,
            0.6,
            0.8,
            0.91
          ],
          [
            0.045,
            0.12,
            0.28,
            0.5,
            0.72
          ]
        ],
        "bloom": {
          "amount": 0.55,
          "radius": 0.027,
          "threshold": 0.43
        },
        "grain": {
          "amount": 0.22,
          "size": 1,
          "color": 0.35
        },
        "vignette": {
          "amount": 0.36,
          "color": "#071c12",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-091",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Aerochrome falso color",
      "description": "Infrarrojo de color simulado: vegetación rosa y azules alterados.",
      "grade": {
        "mixer": [
          0,
          1,
          0,
          0.25,
          0,
          0.75,
          1,
          0,
          0
        ],
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.35,
        "hsl": {
          "red": [
            -22,
            1.2,
            0.01
          ],
          "blue": [
            -15,
            1.2,
            -0.06
          ]
        },
        "grain": {
          "amount": 0.18,
          "size": 1.3,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.25,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-092",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Solarización de plata",
      "description": "Reversión tonal de las luces con solarización y grano de laboratorio.",
      "grade": {
        "mono": true,
        "curve": [
          0.02,
          0.28,
          0.65,
          0.91,
          0.12
        ],
        "grain": {
          "amount": 0.22,
          "size": 1.2,
          "color": 0
        },
        "vignette": {
          "amount": 0.25,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-093",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Solarización cromática",
      "description": "Curvas no monótonas distintas en cada canal para luces surrealistas.",
      "grade": {
        "channels": [
          [
            0.02,
            0.4,
            0.88,
            0.58,
            0.15
          ],
          [
            0.08,
            0.27,
            0.6,
            0.95,
            0.25
          ],
          [
            0.22,
            0.5,
            0.82,
            0.4,
            0.05
          ]
        ],
        "saturation": 1.1,
        "grain": {
          "amount": 0.15,
          "size": 1.2,
          "color": 0.3
        },
        "vignette": {
          "amount": 0.23,
          "color": "#211222",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-094",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Negativo de color",
      "description": "Inversión fotográfica de los colores y de toda la escala tonal.",
      "grade": {
        "invert": true,
        "saturation": 1.15,
        "curve": [
          0.02,
          0.22,
          0.5,
          0.79,
          0.98
        ],
        "grain": {
          "amount": 0.1,
          "size": 0.8,
          "color": 0.2
        }
      }
    },
    {
      "id": "look-095",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Negativo azul",
      "description": "Negativo monocromo convertido en una impresión azul brillante.",
      "grade": {
        "mono": true,
        "invert": true,
        "duotone": [
          "#fff4cb",
          "#b7843f",
          "#1b0921"
        ],
        "curve": [
          0.02,
          0.17,
          0.46,
          0.82,
          0.98
        ],
        "grain": {
          "amount": 0.16,
          "size": 1.6,
          "color": 0
        },
        "vignette": {
          "amount": 0.24,
          "color": "#c9eaff",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-096",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Cartel de seis tintas",
      "description": "Color plano con seis niveles por canal y contraste de impresión.",
      "grade": {
        "curve": [
          0.005,
          0.15,
          0.52,
          0.9,
          1
        ],
        "saturation": 1.7,
        "posterize": 6,
        "vignette": {
          "amount": 0.14,
          "color": "#080808",
          "radius": 0.35,
          "feather": 0.9
        },
        "hsl": {
          "green": [
            -15,
            1.2,
            0
          ],
          "blue": [
            14,
            1.2,
            0
          ]
        }
      }
    },
    {
      "id": "look-097",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Duotono pop rosa y amarillo",
      "description": "Dos tintas de cartel: magenta oscuro y amarillo brillante.",
      "grade": {
        "mono": true,
        "curve": [
          0,
          0.1,
          0.44,
          0.8,
          0.99
        ],
        "duotone": [
          "#541056",
          "#e958ad",
          "#fff29c"
        ],
        "posterize": 9,
        "vignette": {
          "amount": 0.18,
          "color": "#501542",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-098",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Verde de visión nocturna",
      "description": "Monocromo verde luminoso, ruido grueso y bordes casi negros.",
      "grade": {
        "mono": true,
        "exposure": 0.35,
        "curve": [
          0,
          0.2,
          0.55,
          0.83,
          1
        ],
        "duotone": [
          "#001005",
          "#329b3d",
          "#b9ffab"
        ],
        "grain": {
          "amount": 0.6,
          "size": 1,
          "color": 0.45
        },
        "bloom": {
          "amount": 0.3,
          "radius": 0.018,
          "threshold": 0.48
        },
        "vignette": {
          "amount": 0.8,
          "color": "#000500",
          "radius": 0.18,
          "feather": 0.75
        }
      }
    },
    {
      "id": "look-099",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Sueño naranja y lavanda",
      "description": "Colores intercambiados, sombras lavanda y brillos naranja difusos.",
      "grade": {
        "mixer": [
          0.8,
          0.4,
          -0.2,
          -0.15,
          0.35,
          0.8,
          0.45,
          0.55,
          0
        ],
        "curve": [
          0.035,
          0.29,
          0.58,
          0.8,
          0.97
        ],
        "saturation": 0.95,
        "split": {
          "shadows": "#7d55a2",
          "highlights": "#ffba67",
          "amount": 0.46,
          "balance": 0.5
        },
        "bloom": {
          "amount": 0.48,
          "radius": 0.03,
          "threshold": 0.4
        },
        "softness": 0.2,
        "grain": {
          "amount": 0.13,
          "size": 1.6,
          "color": 0.12
        },
        "vignette": {
          "amount": 0.35,
          "color": "#ffd7b6",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    },
    {
      "id": "look-100",
      "category": "experimental",
      "group": "Laboratorio experimental",
      "name": "Veladura de cuarto oscuro",
      "description": "Emulsión casi borrada, fuga roja y crema, polvo y color azul perdido.",
      "grade": {
        "curve": [
          0.17,
          0.36,
          0.55,
          0.7,
          0.87
        ],
        "saturation": 0.42,
        "channels": [
          [
            0.16,
            0.4,
            0.63,
            0.82,
            0.97
          ],
          [
            0.04,
            0.25,
            0.47,
            0.65,
            0.83
          ],
          [
            0.12,
            0.3,
            0.44,
            0.58,
            0.72
          ]
        ],
        "grain": {
          "amount": 0.42,
          "size": 2.8,
          "color": 0.25
        },
        "softness": 0.35,
        "leaks": [
          {
            "color": "#ff3720",
            "amount": 0.95,
            "position": [
              0.02,
              0.45
            ],
            "radius": 0.9,
            "stretch": 0.65
          },
          {
            "color": "#fff1ba",
            "amount": 0.75,
            "position": [
              0.97,
              0.2
            ],
            "radius": 0.7,
            "stretch": 1
          }
        ],
        "dust": {
          "count": 50,
          "amount": 0.6,
          "size": 3.5
        },
        "vignette": {
          "amount": 0.5,
          "color": "#f3d4aa",
          "radius": 0.35,
          "feather": 0.9
        }
      }
    }
  ]
};
