package com.stockflow;

import java.util.HashSet;
import java.util.Set;

public final class OrderWorker {
    private final Set<String> processed = new HashSet<>();
    public synchronized String process(String eventId, String payload) {
        if (eventId == null || eventId.isBlank() || payload == null) throw new IllegalArgumentException("eventId and payload are required");
        if (!processed.add(eventId)) return "DUPLICATE";
        return "ORDER_ACCEPTED";
    }
    public int processedCount() { return processed.size(); }
    public static void main(String[] args) {
        OrderWorker worker = new OrderWorker();
        System.out.println(worker.process("demo-1", "ORDER_CREATED"));
        System.out.println(worker.process("demo-1", "ORDER_CREATED"));
    }
}
