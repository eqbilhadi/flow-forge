<?php

namespace App\Http\Controllers;

use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Models\Workflow;

class WorkflowController extends Controller
{
    public function store(StoreWorkflowRequest $request)
    {
        $nodes = $request->input('nodes');
        $edges = $request->input('edges');

        // Jalankan mesin Topological Sort
        $validation = $this->validateAndSortDAG($nodes, $edges);

        if (!$validation['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi Gagal: Bagan alur tidak boleh memutar (Circular Loop Detected)!',
                'details' => $validation['error']
            ], 422);
        }

        $workflow = Workflow::create([
            'name' => $request->name,
            'description' => $request->description,
            'nodes' => $nodes,
            'edges' => $edges,
            'status' => 'active'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Workflow berhasil disimpan & divalidasi aman!',
            'execution_order' => $validation['order'],
            'data' => $workflow
        ], 201);
    }

    public function index()
    {
        return response()->json(Workflow::all());
    }

    private function validateAndSortDAG($nodes, $edges)
    {
        $nodeIds = array_column($nodes, 'id');
        $inDegree = array_fill_keys($nodeIds, 0);
        $adjacencyList = array_fill_keys($nodeIds, []);

        // 1. Bangun struktur Graph Adjacency List & Hitung In-Degree (Garis Masuk)
        foreach ($edges as $edge) {
            $u = $edge['source'];
            $v = $edge['target'];

            // Antisipasi jika ada id node di edge yang ga terdaftar di nodes
            if (isset($adjacencyList[$u]) && isset($inDegree[$v])) {
                $adjacencyList[$u][] = $v;
                $inDegree[$v]++;
            }
        }

        // 2. Cari semua Node yang ga punya garis masuk (In-Degree = 0) sebagai titik start
        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
            }
        }

        $order = [];

        // 3. Proses Queue (Kahn's Core)
        while (!empty($queue)) {
            $u = array_shift($queue);
            $order[] = $u;

            foreach ($adjacencyList[$u] as $v) {
                $inDegree[$v]--;
                if ($inDegree[$v] === 0) {
                    $queue[] = $v;
                }
            }
        }

        // JIKA jumlah order TIDAK SAMA dengan total node, berarti ada siklus/looping muter!
        if (count($order) !== count($nodes)) {
            return [
                'success' => false,
                'error' => 'Bagan alur mengandung siklus/looping yang menyebabkan deadlock.'
            ];
        }

        return [
            'success' => true,
            'order' => $order
        ];
    }
}
