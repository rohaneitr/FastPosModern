import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('POS Golden Path Checkout (API & DB Assertion)', () => {

  test('Cashier Checkout Flow - Math, State, and API Integrity', async ({ request }) => {
    console.log("Seeding fresh test data...");
    const setupOutput = execSync('docker exec fastpos_backend php e2e_seed.php').toString();
    
    // Clean up PHP output to get only the JSON string
    const jsonStart = setupOutput.indexOf('JSON_START') + 10;
    const jsonEnd = setupOutput.indexOf('JSON_END');
    const jsonStr = setupOutput.substring(jsonStart, jsonEnd);
    const setupData = JSON.parse(jsonStr);

    const backendUrl = 'http://localhost:8002';

    const payload = {
      location_id: setupData.location_id,
      status: 'final',
      payment_method: 'cash',
      tax_rate: 0.10,
      discount_type: 'fixed',
      discount_amount: 0,
      amount_paid: 275,
      items: [
        { product_id: setupData.product1, quantity: 2, price: 100 },
        { product_id: setupData.product2, quantity: 1, price: 50 }
      ],
      transaction_date: new Date().toISOString()
    };

    console.log("Dispatching checkout payload...");
    const startTime = performance.now();
    
    const response = await request.post(`${backendUrl}/api/v1/checkout`, {
      data: payload,
      headers: {
        'Authorization': `Bearer ${setupData.token}`,
        'Accept': 'application/json'
      }
    });

    const endTime = performance.now();
    const latency = endTime - startTime;

    console.log(`[Performance] API Latency: ${latency.toFixed(2)}ms`);

    if (!response.ok()) {
      const err = await response.json();
      console.log("Payload Failed:", err);
      
      console.log("--- SNAPSHOT DEBUG ---");
      const dbStateStr = execSync(`docker exec fastpos_backend php e2e_verify.php --product1=${setupData.product1} --product2=${setupData.product2}`).toString();
      console.log(dbStateStr);
    }
    
    expect(response.ok()).toBeTruthy();
    
    const responseData = await response.json();
    expect(responseData.message).toBe('Sale processed successfully');
    expect(Number(responseData.final_total)).toBe(275); 

    console.log("Verifying Database State Transitions...");
    const dbPostStateOut = execSync(`docker exec fastpos_backend php e2e_verify.php --product1=${setupData.product1} --product2=${setupData.product2} --txId=${responseData.transaction_id}`).toString();

    const dbJsonStart = dbPostStateOut.indexOf('JSON_START') + 10;
    const dbJsonEnd = dbPostStateOut.indexOf('JSON_END');
    const dbJsonStr = dbPostStateOut.substring(dbJsonStart, dbJsonEnd);
    const dbPostState = JSON.parse(dbJsonStr);

    expect(dbPostState.stock1).toBe('98.0000');
    expect(dbPostState.stock2).toBe('49.0000');
    expect(dbPostState.tx_total).toBe('275.0000');
    expect(dbPostState.tx_lines_count).toBe(2);
    expect(dbPostState.tx_payment_amount).toBe('275.0000');
    
    console.log("E2E Mathematical Verification PASSED!");
  });
});
