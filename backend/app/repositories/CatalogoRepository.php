<?php

namespace App\Repositories;

use Config\Database;
use PDO;
use InvalidArgumentException;

class CatalogoRepository
{
    private PDO $db;

    private array $sections = [
        'categorias' => [
            'table' => 'categoria',
            'idColumn' => 'id_categoria',
            'nameColumn' => 'nombre_categoria',
            'fields' => ['descripcion', 'estado'],
        ],
        'unidades' => [
            'table' => 'unidad',
            'idColumn' => 'id_unidad',
            'nameColumn' => 'nombre_unidad',
            'fields' => ['simbolo', 'estado'],
        ],
        'tipo_insumo' => [
            'table' => 'tipo_insumo',
            'idColumn' => 'id_tipo_insumo',
            'nameColumn' => 'nombre_tipo',
            'fields' => ['descripcion', 'estado'],
        ],
        'colores' => [
            'table' => 'color',
            'idColumn' => 'id_color',
            'nameColumn' => 'nombre_color',
            'fields' => ['codigo_hex', 'estado'],
        ],
        'variedades' => [
            'table' => 'variedad',
            'idColumn' => 'id_variedad',
            'nameColumn' => 'nombre_variedad',
            'fields' => ['descripcion', 'estado'],
        ],
    ];

    public function __construct()
    {
        $this->db = (new Database())->connect();
    }

    private function getSectionConfig(string $section): array
    {
        if (!isset($this->sections[$section])) {
            throw new InvalidArgumentException('Sección inválida para catálogo');
        }

        return $this->sections[$section];
    }

    public function all(string $section): array
    {
        $config = $this->getSectionConfig($section);
        $query = "SELECT * FROM {$config['table']} WHERE estado = 1 ORDER BY {$config['idColumn']} DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $section, int $id): ?array
    {
        $config = $this->getSectionConfig($section);
        $query = "SELECT * FROM {$config['table']} WHERE {$config['idColumn']} = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public function create(string $section, array $data): int
    {
        $config = $this->getSectionConfig($section);
        $columns = array_merge([$config['nameColumn']], $config['fields']);
        $values = array_map(fn($column) => ":{$column}", $columns);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $config['table'],
            implode(', ', $columns),
            implode(', ', $values)
        );

        $stmt = $this->db->prepare($query);

        foreach ($columns as $column) {
            $value = $data[$column] ?? null;
            $stmt->bindValue(":{$column}", $value, $column === 'estado' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }

    public function update(string $section, int $id, array $data): bool
    {
        $config = $this->getSectionConfig($section);
        $columns = array_merge([$config['nameColumn']], $config['fields']);
        $set = implode(', ', array_map(fn($column) => "$column = :$column", $columns));

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $config['table'],
            $set,
            $config['idColumn']
        );

        $stmt = $this->db->prepare($query);

        foreach ($columns as $column) {
            $value = $data[$column] ?? null;
            $stmt->bindValue(":{$column}", $value, $column === 'estado' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete(string $section, int $id): bool
    {
        $config = $this->getSectionConfig($section);
        $query = sprintf(
            'UPDATE %s SET estado = 0 WHERE %s = :id',
            $config['table'],
            $config['idColumn']
        );

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
