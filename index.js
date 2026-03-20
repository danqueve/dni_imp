const { getByDni } = require('cuitonline');

async function buscar() {
  const resultado = await getByDni('16987641'); // reemplazá con un DNI real
  console.log(resultado);
}

buscar();
